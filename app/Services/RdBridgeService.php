<?php

namespace App\Services;

use App\Repositories\AtivoRepository;
use App\Repositories\RdBridgeRepository;

/**
 * RD.Bridge -- coletor instalado numa unidade remota (atrás de NAT),
 * fala com este servidor por conexão de saída (mesmo espírito do agente
 * Windows: checkin/heartbeat, header de chave, nunca porta aberta).
 *
 * v1: só relay de coleta SNMP (ver heartbeat()/registrarResultadoColeta())
 * -- os ativos elegíveis já existem no cadastro (criados manualmente,
 * como qualquer switch/roteador com SNMP habilitado hoje), o Bridge só
 * executa a coleta que o próprio servidor faria se alcançasse a rede
 * direto. Coleta de DVR/NVR via Bridge fica pra uma próxima versão --
 * a API do Intelbras tem detalhes de firmware descobertos só na prática
 * (ver IntelbrasDvrService) que não dá pra portar às cegas sem testar
 * contra o equipamento real.
 */
class RdBridgeService
{
    private RdBridgeRepository $repository;
    private AtivoRepository $ativoRepository;

    public function __construct()
    {
        $this->repository = new RdBridgeRepository();
        $this->ativoRepository = new AtivoRepository();
    }

    public function listar(): array
    {
        return $this->repository->listar();
    }

    public function buscar(int $id): ?array
    {
        return $this->repository->buscarPorId($id);
    }

    /** @return array{success: bool, message: string, id?: int, token?: string} */
    public function criar(int $unidadeId, string $nome, string $modo): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['success' => false, 'message' => 'Informe um nome pra identificar o coletor.'];
        }

        if (!(new UnidadeService())->buscar($unidadeId)) {
            return ['success' => false, 'message' => 'Unidade inválida.'];
        }

        if (!in_array($modo, ['http', 'vpn'], true)) {
            $modo = 'http';
        }

        $token = bin2hex(random_bytes(32));
        $id = $this->repository->criar($unidadeId, $nome, $token, $modo);

        AuditService::registrar('Integrações', 'RD.Bridge', "Coletor \"{$nome}\" criado.");

        return ['success' => true, 'message' => 'Coletor criado. Copie o token agora -- ele não é mostrado de novo.', 'id' => $id, 'token' => $token];
    }

    public function revogar(int $id): array
    {
        $bridge = $this->repository->buscarPorId($id);
        if (!$bridge) {
            return ['success' => false, 'message' => 'Coletor não encontrado.'];
        }

        $this->repository->revogar($id);

        AuditService::registrar('Integrações', 'RD.Bridge', "Coletor \"{$bridge['nome']}\" revogado.");

        return ['success' => true, 'message' => 'Coletor revogado -- ele não consegue mais se autenticar.'];
    }

    public function autenticar(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        return $this->repository->buscarPorToken($token);
    }

    /**
     * Chamado a cada heartbeat do Bridge -- devolve a lista de ativos da
     * unidade dele que precisam de coleta SNMP, com o suficiente pra
     * executar (ip, community, tipo). O próprio Bridge decide o
     * intervalo entre uma rodada e outra (equivalente ao IntervaloMinutos
     * do agente Windows).
     */
    public function heartbeat(array $bridge, string $ipOrigem, ?string $versao): array
    {
        $this->repository->registrarCheckin((int)$bridge['id'], $ipOrigem, $versao);

        $ativos = $this->ativoRepository->listarComSnmpHabilitadoPorUnidade((int)$bridge['unidade_id']);

        $comunidadePadrao = (new AtivoService())->comunidadePadrao();

        $jobs = array_map(static fn (array $a) => [
            'ativo_id' => (int)$a['id'],
            'ip' => $a['ip'],
            'community' => $a['snmp_community'] ?: $comunidadePadrao,
            'tipo_slug' => $a['tipo_slug'] ?? '',
        ], $ativos);

        return ['success' => true, 'jobs' => $jobs];
    }

    /**
     * Aplica o resultado de uma coleta SNMP feita pelo Bridge -- mesmo
     * merge não-destrutivo de AtivoService::coletarSnmp(), só que o
     * comando SNMP em si rodou remotamente, não neste servidor. Confere
     * que o ativo pertence mesmo à unidade do Bridge que está mandando o
     * resultado, pra um coletor nunca conseguir escrever fora da própria
     * unidade mesmo que o ativo_id seja adivinhado.
     */
    public function registrarResultadoColeta(array $bridge, int $ativoId, array $dadosColetados): array
    {
        $ativo = $this->ativoRepository->buscarPorId($ativoId);

        if (!$ativo || (int)$ativo['unidade_id'] !== (int)$bridge['unidade_id']) {
            return ['success' => false, 'message' => 'Ativo não encontrado nesta unidade.'];
        }

        if (empty($dadosColetados)) {
            return ['success' => true, 'message' => 'Nada coletado (dispositivo não respondeu).'];
        }

        $detalhesAtuais = json_decode($ativo['detalhes'] ?? '', true) ?: [];
        $detalhesNovos = array_merge($detalhesAtuais, $dadosColetados);

        $this->ativoRepository->atualizarDetalhesSnmp($ativoId, json_encode($detalhesNovos, JSON_UNESCAPED_UNICODE));

        return ['success' => true, 'message' => 'Dados aplicados.'];
    }
}
