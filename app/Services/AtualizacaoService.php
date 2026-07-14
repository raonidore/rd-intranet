<?php

namespace App\Services;

use App\Repositories\AtualizacaoRepository;

class AtualizacaoService
{
    private const SCRIPT_VERIFICAR = '/opt/rdtecnologia/scripts/atualizar_verificar_web.sh';
    private const SCRIPT_APLICAR = '/opt/rdtecnologia/scripts/atualizar_aplicar_web.sh';
    private const SCRIPT_REVERTER = '/opt/rdtecnologia/scripts/atualizar_reverter_web.sh';
    private const BRANCH = 'main';

    private LinuxService $linux;
    private AtualizacaoRepository $repo;
    private MigrationService $migrations;

    public function __construct()
    {
        $this->linux = new LinuxService();
        $this->repo = new AtualizacaoRepository();
        $this->migrations = new MigrationService();
    }

    public function commitAtual(): ?string
    {
        return $this->git('rev-parse HEAD');
    }

    public function commitRemoto(): ?string
    {
        return $this->git('rev-parse origin/' . self::BRANCH);
    }

    /**
     * Dados legiveis de um commit (hash, data, assunto), pra mostrar em
     * vez do hash cru na tela -- ex: commitInfo('HEAD') ou
     * commitInfo('origin/main').
     *
     * @return array{hash: string, data: string, assunto: string}|null
     */
    public function commitInfo(string $ref): ?array
    {
        $formato = "%H\t%ad\t%s";
        $formatoData = 'format:%d/%m/%Y %H:%M';
        $resultado = $this->linux->executar(
            $this->gitPrefixo()
            . ' log -1 --date=' . escapeshellarg($formatoData)
            . ' --format=' . escapeshellarg($formato)
            . ' ' . escapeshellarg($ref)
        );

        if (!$resultado['success'] || trim($resultado['output']) === '') {
            return null;
        }

        $partes = explode("\t", trim($resultado['output']), 3);
        if (count($partes) < 3) {
            return null;
        }

        return ['hash' => $partes[0], 'data' => $partes[1], 'assunto' => $partes[2]];
    }

    /**
     * Commits que existem em origin/main e ainda nao em HEAD, mais recente
     * primeiro. So le refs locais (sem rede) -- reflete o estado de acordo
     * com o ultimo fetch feito por verificar()/aplicar().
     *
     * @return array<int, array{hash: string, autor: string, data: string, assunto: string}>
     */
    public function commitsPendentes(): array
    {
        return $this->commitsEntre('HEAD', 'origin/' . self::BRANCH);
    }

    /**
     * Lista de commits (mais recente primeiro) entre duas referencias --
     * usado tanto pros pendentes (HEAD..origin/main) quanto pra descricao
     * de uma atualizacao ja aplicada (commit_antes..commit_depois do
     * historico).
     *
     * @return array<int, array{hash: string, autor: string, data: string, assunto: string}>
     */
    private function commitsEntre(string $de, string $para): array
    {
        $formato = "%H\t%an\t%ad\t%s";
        $resultado = $this->linux->executar(
            $this->gitPrefixo()
            . ' log ' . escapeshellarg($de . '..' . $para)
            . ' --date=iso --format=' . escapeshellarg($formato)
        );

        if (!$resultado['success'] || trim($resultado['output']) === '') {
            return [];
        }

        $commits = [];
        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $partes = explode("\t", $linha, 4);
            if (count($partes) < 4) continue;

            $commits[] = [
                'hash' => $partes[0],
                'autor' => $partes[1],
                'data' => $partes[2],
                'assunto' => $partes[3],
            ];
        }

        return $commits;
    }

    /**
     * O que uma entrada do histórico (aplicar/reverter) trouxe/desfez, em
     * commits legíveis -- pra "Descrição" no histórico, em vez de só o
     * intervalo de hash. Num 'aplicar', o intervalo cronológico é
     * commit_antes..commit_depois (o que entrou); num 'reverter', é o
     * inverso -- commit_depois é o alvo (mais antigo) e commit_antes é de
     * onde saiu, então os commits "desfeitos" são commit_depois..commit_antes.
     *
     * @return array<int, array{hash: string, autor: string, data: string, assunto: string}>
     */
    public function descricaoHistorico(int $id): array
    {
        $registro = $this->repo->buscarPorId($id);

        if (!$registro || !$registro['commit_antes'] || !$registro['commit_depois']) {
            return [];
        }

        return $registro['tipo'] === 'reverter'
            ? $this->commitsEntre($registro['commit_depois'], $registro['commit_antes'])
            : $this->commitsEntre($registro['commit_antes'], $registro['commit_depois']);
    }

    public function podeReverter(): bool
    {
        return $this->repo->ultimoSucesso('aplicar') !== null;
    }

    public function historico(int $limite = 20): array
    {
        return $this->repo->listar($limite);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verificar(): array
    {
        $resultado = $this->executarScript(self::SCRIPT_VERIFICAR);

        ConfigService::set('atualizacao_verificado_em', date('Y-m-d H:i:s'));
        ConfigService::set('atualizacao_ultimo_erro', $resultado['success'] ? '' : $resultado['message']);

        return $resultado;
    }

    /**
     * @return array{success: bool, message: string, commit_antes: ?string, commit_depois: ?string}
     */
    public function aplicar(?int $usuarioId): array
    {
        $commitAntes = $this->commitAtual();

        $resultado = $this->executarScript(self::SCRIPT_APLICAR);
        $sucesso = $resultado['success'];
        $saida = $resultado['message'];

        if ($sucesso) {
            $migracao = $this->migrations->aplicar();
            $sucesso = $migracao['success'];

            $saida .= $migracao['success']
                ? (' ' . (empty($migracao['aplicadas'])
                    ? 'Nenhuma migration pendente.'
                    : 'Migrations aplicadas: ' . implode(', ', $migracao['aplicadas']) . '.'))
                : (' Erro ao aplicar migrations: ' . $migracao['erro']);
        }

        if ($sucesso) {
            $this->garantirCronsColeta();
        }

        $commitDepois = $this->commitAtual();

        $this->repo->registrar('aplicar', $commitAntes, $commitDepois, $sucesso, $saida, $usuarioId);

        ConfigService::set('atualizacao_verificado_em', date('Y-m-d H:i:s'));
        ConfigService::set('atualizacao_ultimo_erro', $sucesso ? '' : $saida);

        return [
            'success' => $sucesso,
            'message' => $saida,
            'commit_antes' => $commitAntes,
            'commit_depois' => $commitDepois,
        ];
    }

    /**
     * Reverte para o commit_antes da ultima atualizacao aplicada com
     * sucesso. Nao aceita nenhum commit vindo de fora do proprio historico
     * de atualizacoes do sistema.
     *
     * @return array{success: bool, message: string}
     */
    public function reverter(?int $usuarioId): array
    {
        $ultimo = $this->repo->ultimoSucesso('aplicar');

        if (!$ultimo || !$ultimo['commit_antes']) {
            return ['success' => false, 'message' => 'Não há uma atualização aplicada para reverter.'];
        }

        $commitAntesRevert = $this->commitAtual();
        $alvo = $ultimo['commit_antes'];

        $resultado = $this->executarScript(self::SCRIPT_REVERTER, [$alvo]);

        $this->repo->registrar(
            'reverter',
            $commitAntesRevert,
            $resultado['success'] ? $alvo : null,
            $resultado['success'],
            $resultado['message'],
            $usuarioId
        );

        return $resultado;
    }

    /**
     * Garante que os cron nativos de coleta (trafego de rede, contadores e
     * logs do firewall) existem -- mesma logica de scripts/install.sh, so
     * reaplicada a cada atualizacao (idempotente: nao cria duplicado se o
     * comando ja existir). Cobre o caso de um servidor que ainda nao tinha
     * algum desses jobs quando foi instalado.
     */
    public function garantirCronsColeta(): void
    {
        $this->garantirCronJob(
            'Coleta de tráfego de rede',
            'Grava snapshot de RX/TX (bytes e pacotes) por interface para o histórico de tráfego (Infraestrutura > Network > Tráfego > Histórico).',
            '*/5 * * * *',
            '/usr/bin/php ' . $this->repoDir() . '/scripts/system/coletar_trafego.php'
        );

        $this->garantirCronJob(
            'Coleta de contadores do firewall',
            'Grava snapshot de pacotes/bytes por regra ativa do firewall, para o gráfico de regras mais acionadas (Infraestrutura > Firewall > Ao Vivo).',
            '*/5 * * * *',
            '/usr/bin/php ' . $this->repoDir() . '/scripts/system/coletar_contadores_iptables.php'
        );

        $this->garantirCronJob(
            'Coleta de logs do firewall',
            'Grava os IPs bloqueados/liberados registrados pelas regras do firewall, para o ranking de IPs (Infraestrutura > Firewall > Ao Vivo).',
            '*/2 * * * *',
            '/usr/bin/php ' . $this->repoDir() . '/scripts/system/coletar_logs_iptables.php'
        );
    }

    private function garantirCronJob(string $nome, string $descricao, string $expressao, string $comando): void
    {
        $stmt = \App\Core\Database::connection()->prepare(
            'SELECT COUNT(*) FROM cron_jobs WHERE comando = ?'
        );
        $stmt->execute([$comando]);

        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        (new CronService())->criar([
            'nome' => $nome,
            'descricao' => $descricao,
            'expressao' => $expressao,
            'usuario_execucao' => 'www-data',
            'comando' => $comando,
            'ativo' => true,
        ]);
    }

    private function executarScript(string $script, array $parametros = []): array
    {
        $resultado = $this->linux->executarScript($script, $parametros);
        $dados = json_decode(trim($resultado['output']), true);

        if (is_array($dados) && isset($dados['success'])) {
            return ['success' => (bool)$dados['success'], 'message' => (string)($dados['message'] ?? '')];
        }

        // sudo pode falhar antes do script rodar (ex: sudoers nao liberado
        // ainda) -- nesse caso a saida nao vai ser JSON valido.
        return ['success' => false, 'message' => $resultado['output']];
    }

    private function git(string $args): ?string
    {
        $resultado = $this->linux->executar($this->gitPrefixo() . ' ' . $args);

        return $resultado['success'] ? trim($resultado['output']) : null;
    }

    /**
     * 'safe.directory' evita o "detected dubious ownership" do git: o
     * checkout pertence ao usuario dono do deploy (ex: 'ti'), e o PHP roda
     * como www-data -- sem isso, todo comando de leitura abaixo falharia
     * silenciosamente (git recusa a rodar em repo de outro dono por
     * padrao desde a CVE-2022-24765).
     */
    private function gitPrefixo(): string
    {
        $dir = $this->repoDir();

        return 'git -c ' . escapeshellarg('safe.directory=' . $dir) . ' -C ' . escapeshellarg($dir);
    }

    private function repoDir(): string
    {
        return realpath(__DIR__ . '/../..');
    }
}
