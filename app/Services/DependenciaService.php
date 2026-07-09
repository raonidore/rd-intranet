<?php

namespace App\Services;

class DependenciaService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    /**
     * Junta o catalogo (nome/descricao/obrigatorio) com o status real
     * (instalado ou nao) lido do servidor.
     */
    public function checklist(): array
    {
        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/dependencias_status_web.sh');

        $instalado = [];
        foreach (explode("\n", trim($resultado['output'])) as $linha) {
            $linha = trim($linha);
            if ($linha === '' || !str_contains($linha, '|')) continue;

            [$chave, $estado] = explode('|', $linha, 2);
            $instalado[$chave] = trim($estado) === '1';
        }

        $itens = [];
        foreach (DependenciaCatalogo::itens() as $item) {
            $item['instalado'] = $instalado[$item['chave']] ?? null;
            $itens[] = $item;
        }

        return $itens;
    }

    public function instalar(string $chave): array
    {
        if (!DependenciaCatalogo::item($chave)) {
            return ['success' => false, 'message' => 'Ferramenta desconhecida.'];
        }

        // apt update + install pode demorar mais que o max_execution_time padrao
        set_time_limit(180);

        $resultado = $this->linux->executarScript('/opt/rdtecnologia/scripts/dependencias_instalar_web.sh', [$chave]);

        // o apt-get e barulhento (log de instalacao inteiro no stdout); o
        // script sempre imprime o JSON por ultimo, entao so a ultima linha
        // e o resultado de verdade -- decodificar a saida inteira falharia
        // e faria uma instalacao bem-sucedida parecer erro
        $linhas = explode("\n", trim($resultado['output']));
        $ultimaLinha = trim((string)end($linhas));
        $dados = json_decode($ultimaLinha, true);

        if (is_array($dados)) {
            $dados['saida_completa'] = $resultado['output'];
            return $dados;
        }

        return ['success' => false, 'message' => $resultado['output']];
    }
}
