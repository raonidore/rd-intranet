<?php

namespace App\Services;

class SambaAclService
{
    private LinuxService $linux;

    public function __construct()
    {
        $this->linux = new LinuxService();
    }

    public function listar(): array
    {
        $resultado = $this->linux->executarScript(
            '/opt/rdtecnologia/scripts/acl_samba_web.sh'
        );

        return $this->parse($resultado['output'] ?? '');
    }

    private function parse(string $output): array
    {
        $shares = [];
        $blocos = preg_split('/### SHARE:/', $output);

        foreach ($blocos as $bloco) {
            $bloco = trim($bloco);

            if ($bloco === '') {
                continue;
            }

            $linhas = explode("\n", $bloco);
            $nome = trim(array_shift($linhas));

            $path = '';
            $regras = [];

            foreach ($linhas as $linha) {
                $linha = trim($linha);

                if ($linha === '' || str_starts_with($linha, '#')) {
                    continue;
                }

                if (str_starts_with($linha, 'PATH=')) {
                    $path = substr($linha, 5);
                    continue;
                }

                $traduzida = $this->traduzirLinhaAcl($linha);

                if ($traduzida) {
                    $regras[] = $traduzida;
                }
            }

            $shares[] = [
                'nome' => $nome,
                'path' => $path,
                'regras' => $regras,
            ];
        }

        return $shares;
    }

    private function traduzirLinhaAcl(string $linha): ?array
    {
        if (!str_contains($linha, ':')) {
            return null;
        }

        $partes = explode(':', $linha);

        $tipo = $partes[0] ?? '';
        $entidade = $partes[1] ?? '';
        $permissao = $partes[2] ?? '';

        if (!in_array($tipo, ['user', 'group', 'mask', 'other', 'default'], true)) {
            return null;
        }

        return [
            'original' => $linha,
            'tipo' => $this->traduzirTipo($tipo, $entidade),
            'entidade' => $entidade ?: $this->entidadePadrao($tipo),
            'permissao' => $permissao,
            'leitura' => str_contains($permissao, 'r'),
            'escrita' => str_contains($permissao, 'w'),
            'execucao' => str_contains($permissao, 'x'),
            'humano' => $this->traduzirPermissao($permissao),
        ];
    }

    private function traduzirTipo(string $tipo, string $entidade): string
    {
        return match ($tipo) {
            'user' => $entidade ? 'Usuário específico' : 'Dono da pasta',
            'group' => $entidade ? 'Grupo específico' : 'Grupo da pasta',
            'mask' => 'Máscara efetiva',
            'other' => 'Outros',
            'default' => 'Herança padrão',
            default => $tipo,
        };
    }

    private function entidadePadrao(string $tipo): string
    {
        return match ($tipo) {
            'user' => 'dono',
            'group' => 'grupo',
            'mask' => 'máscara',
            'other' => 'outros',
            default => '-',
        };
    }

    private function traduzirPermissao(string $p): string
    {
        $partes = [];

        $partes[] = str_contains($p, 'r') ? 'ler' : 'não lê';
        $partes[] = str_contains($p, 'w') ? 'gravar' : 'não grava';
        $partes[] = str_contains($p, 'x') ? 'entrar/listar' : 'não entra';

        return implode(', ', $partes);
    }
}
