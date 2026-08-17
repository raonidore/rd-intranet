<?php

namespace App\Services;

/**
 * Politica de senha configuravel em Administracao > Usuarios > Política de
 * senha -- guardada via ConfigService (mesmo esquema chave/valor usado pelo
 * EmailService), unica por instalacao. Usada tanto na criacao/redefinicao
 * de senha por admin quanto na troca pelo proprio usuario (Meu Perfil) e no
 * fluxo de "esqueci minha senha".
 */
class PasswordPolicyService
{
    private const CHAVE_COMPRIMENTO = 'senha_politica_comprimento_minimo';
    private const CHAVE_MAIUSCULA_MINUSCULA = 'senha_politica_maiuscula_minuscula';
    private const CHAVE_NUMERO = 'senha_politica_numero';
    private const CHAVE_ESPECIAL = 'senha_politica_especial';
    private const CHAVE_DADOS_OBVIOS = 'senha_politica_dados_obvios';

    private const COMPRIMENTO_MINIMO_ABSOLUTO = 4;
    private const COMPRIMENTO_MAXIMO_ABSOLUTO = 64;

    /** Sequencias numericas simples (e sua ordem invertida) tratadas como "obvias". */
    private const SEQUENCIAS_OBVIAS = [
        '0123', '1234', '2345', '3456', '4567', '5678', '6789',
        '9876', '8765', '7654', '6543', '5432', '4321', '3210',
    ];

    /** Senhas/trechos comuns o bastante pra bloquear mesmo sem checar contra dados do usuario. */
    private const TERMOS_COMUNS = ['123456', 'senha', 'password', 'qwerty', '111111', '000000', 'abcdef'];

    public static function politica(): array
    {
        return [
            'comprimento_minimo' => self::comprimentoMinimo(),
            'maiuscula_minuscula' => ConfigService::get(self::CHAVE_MAIUSCULA_MINUSCULA, '0') === '1',
            'numero' => ConfigService::get(self::CHAVE_NUMERO, '0') === '1',
            'especial' => ConfigService::get(self::CHAVE_ESPECIAL, '0') === '1',
            'dados_obvios' => ConfigService::get(self::CHAVE_DADOS_OBVIOS, '0') === '1',
        ];
    }

    public static function comprimentoMinimo(): int
    {
        $valor = (int)(ConfigService::get(self::CHAVE_COMPRIMENTO, '8') ?: '8');

        return max(self::COMPRIMENTO_MINIMO_ABSOLUTO, min(self::COMPRIMENTO_MAXIMO_ABSOLUTO, $valor));
    }

    /** Igual a politica(), mas com chaves em camelCase pra passar direto como JSON pro JS. */
    public static function politicaParaJs(): array
    {
        $p = self::politica();

        return [
            'comprimentoMinimo' => $p['comprimento_minimo'],
            'maiusculaMinuscula' => $p['maiuscula_minuscula'],
            'numero' => $p['numero'],
            'especial' => $p['especial'],
            'dadosObvios' => $p['dados_obvios'],
        ];
    }

    public static function salvar(array $dados): void
    {
        $comprimento = (int)($dados['comprimento_minimo'] ?? 8);
        $comprimento = max(self::COMPRIMENTO_MINIMO_ABSOLUTO, min(self::COMPRIMENTO_MAXIMO_ABSOLUTO, $comprimento));

        ConfigService::set(self::CHAVE_COMPRIMENTO, (string)$comprimento);
        ConfigService::set(self::CHAVE_MAIUSCULA_MINUSCULA, !empty($dados['maiuscula_minuscula']) ? '1' : '0');
        ConfigService::set(self::CHAVE_NUMERO, !empty($dados['numero']) ? '1' : '0');
        ConfigService::set(self::CHAVE_ESPECIAL, !empty($dados['especial']) ? '1' : '0');
        ConfigService::set(self::CHAVE_DADOS_OBVIOS, !empty($dados['dados_obvios']) ? '1' : '0');
    }

    /**
     * @param array{nome?: string, login?: string, email?: string} $contexto dados do dono da senha, pra checar "dados obvios"
     * @return string[] mensagens de erro (vazio = senha valida pra politica atual)
     */
    public static function validar(string $senha, array $contexto = []): array
    {
        $politica = self::politica();
        $erros = [];

        if (mb_strlen($senha) < $politica['comprimento_minimo']) {
            $erros[] = "A senha deve ter pelo menos {$politica['comprimento_minimo']} caracteres.";
        }

        if ($politica['maiuscula_minuscula'] && !(preg_match('/[A-Z]/', $senha) && preg_match('/[a-z]/', $senha))) {
            $erros[] = 'A senha deve ter letras maiúsculas e minúsculas.';
        }

        if ($politica['numero'] && !preg_match('/[0-9]/', $senha)) {
            $erros[] = 'A senha deve ter pelo menos um número.';
        }

        if ($politica['especial'] && !preg_match('/[^A-Za-z0-9]/u', $senha)) {
            $erros[] = 'A senha deve ter pelo menos um caractere especial (!, @, #, $, etc.).';
        }

        if ($politica['dados_obvios'] && self::contemDadosObvios($senha, $contexto)) {
            $erros[] = 'A senha não pode conter seu nome, login, e-mail ou sequências óbvias (ex: "123456").';
        }

        return $erros;
    }

    private static function contemDadosObvios(string $senha, array $contexto): bool
    {
        $senhaBaixa = mb_strtolower($senha);

        foreach (['nome', 'login', 'email'] as $campo) {
            $valor = mb_strtolower(trim((string)($contexto[$campo] ?? '')));
            if ($valor === '') {
                continue;
            }

            $partes = $campo === 'nome' ? preg_split('/\s+/', $valor) : [explode('@', $valor)[0]];
            foreach ($partes as $parte) {
                if (mb_strlen($parte) >= 3 && str_contains($senhaBaixa, $parte)) {
                    return true;
                }
            }
        }

        foreach (self::SEQUENCIAS_OBVIAS as $sequencia) {
            if (str_contains($senha, $sequencia)) {
                return true;
            }
        }

        foreach (self::TERMOS_COMUNS as $termo) {
            if (str_contains($senhaBaixa, $termo)) {
                return true;
            }
        }

        // 4+ caracteres identicos seguidos (ex: "aaaa", "1111")
        if (preg_match('/(.)\1{3,}/u', $senha)) {
            return true;
        }

        return false;
    }
}
