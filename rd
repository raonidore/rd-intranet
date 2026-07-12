#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$comando = $argv[1] ?? null;
$nome = $argv[2] ?? null;

if (!$comando) {
    echo "RD CLI\n\n";
    echo "Comandos disponíveis:\n";
    echo "  make:controller NomeController\n";
    echo "  make:service NomeService\n";
    echo "  make:repository NomeRepository\n";
    echo "  make:job NomeJob\n";
    echo "  make:module NomeModulo\n";
    echo "  migrate                 Aplica as migrations pendentes em database/migrations/\n";
    echo "  atualizacao:verificar   Busca origin/main e atualiza o cache de 'há atualização?'\n";
    echo "  antivirus:verificar     Escaneia os compartilhamentos do Samba em busca de ameaças\n";
    exit;
}

function criarArquivo(string $caminho, string $conteudo): void
{
    if (file_exists($caminho)) {
        echo "Arquivo já existe: {$caminho}\n";
        exit(1);
    }

    file_put_contents($caminho, $conteudo);
    echo "Criado: {$caminho}\n";
}

function garantirNome(?string $nome): string
{
    if (!$nome) {
        echo "Informe o nome.\n";
        exit(1);
    }

    return $nome;
}

switch ($comando) {
    case 'make:controller':
        $nome = garantirNome($nome);
        criarArquivo(
            __DIR__ . "/app/Controllers/{$nome}.php",
            "<?php\n\nnamespace App\\Controllers;\n\nuse App\\Core\\Controller;\n\nclass {$nome} extends Controller\n{\n    public function index(): void\n    {\n        //\n    }\n}\n"
        );
        break;

    case 'make:service':
        $nome = garantirNome($nome);
        criarArquivo(
            __DIR__ . "/app/Services/{$nome}.php",
            "<?php\n\nnamespace App\\Services;\n\nclass {$nome}\n{\n    //\n}\n"
        );
        break;

    case 'make:repository':
        $nome = garantirNome($nome);
        criarArquivo(
            __DIR__ . "/app/Repositories/{$nome}.php",
            "<?php\n\nnamespace App\\Repositories;\n\nuse App\\Core\\Database;\nuse PDO;\n\nclass {$nome}\n{\n    private PDO \$pdo;\n\n    public function __construct()\n    {\n        \$this->pdo = Database::connection();\n    }\n}\n"
        );
        break;

    case 'make:job':
        $nome = garantirNome($nome);
        criarArquivo(
            __DIR__ . "/app/Jobs/{$nome}.php",
            "<?php\n\nnamespace App\\Jobs;\n\nclass {$nome} extends AbstractJob\n{\n    public function __construct()\n    {\n        // \$this->addStep('Nome da etapa', function () {\n        //     return 'OK';\n        // });\n    }\n\n    public function name(): string\n    {\n        return '{$nome}';\n    }\n}\n"
        );
        break;

    case 'make:module':
        $nome = garantirNome($nome);
        $base = __DIR__ . "/app/Modules/{$nome}";

        $dirs = [
            "{$base}/Controllers",
            "{$base}/Services",
            "{$base}/Repositories",
            "{$base}/Views",
            "{$base}/Routes",
            "{$base}/Config",
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
                echo "Criado diretório: {$dir}\n";
            }
        }
        break;

    case 'migrate':
        $resultado = (new \App\Services\MigrationService())->aplicar();

        foreach ($resultado['aplicadas'] as $arquivo) {
            echo "Aplicada: {$arquivo}\n";
        }

        if (!$resultado['success']) {
            echo "Erro: {$resultado['erro']}\n";
            exit(1);
        }

        if (empty($resultado['aplicadas'])) {
            echo "Nada a aplicar, banco já está em dia.\n";
        }

        break;

    case 'atualizacao:verificar':
        $resultado = (new \App\Services\AtualizacaoService())->verificar();

        echo ($resultado['success'] ? 'OK: ' : 'ERRO: ') . $resultado['message'] . "\n";

        if (!$resultado['success']) {
            exit(1);
        }

        break;

    case 'antivirus:verificar':
        $resultado = (new \App\Services\AntivirusService())->verificarAgora(null, 'agendada');

        echo ($resultado['success'] ? 'OK: ' : 'ERRO: ') . $resultado['message'] . "\n";

        if (!$resultado['success']) {
            exit(1);
        }

        break;

    case 'speedtest:executar':
        $resultado = (new \App\Services\SpeedtestService())->executar();

        echo ($resultado['success'] ? 'OK: ' : 'ERRO: ') . $resultado['message'] . "\n";

        if (!$resultado['success']) {
            exit(1);
        }

        break;

    case 'ddns:atualizar':
        $resultado = (new \App\Services\DdnsService())->atualizarTodas();

        echo ($resultado['success'] ? 'OK: ' : 'ERRO: ') . $resultado['message'] . "\n";

        if (!$resultado['success']) {
            exit(1);
        }

        break;

    case 'vpn:coletar-wireguard':
        (new \App\Services\VpnWireguardService())->coletarTrafego();
        echo "OK: snapshot de tráfego WireGuard coletado.\n";

        break;

    case 'vpn:coletar-openvpn':
        (new \App\Services\VpnOpenvpnService())->coletarTrafego();
        echo "OK: snapshot de tráfego OpenVPN coletado.\n";

        break;

    case 'vpn:coletar-ikev2':
        (new \App\Services\VpnIkev2Service())->coletarTrafego();
        echo "OK: snapshot de tráfego IKEv2 coletado.\n";

        break;

    case 'ativos:coletar-snmp':
        $resultado = (new \App\Services\AtivoService())->coletarSnmpTodos();
        echo "OK: SNMP coletado em {$resultado['sucesso']}/{$resultado['total']} ativo(s).\n";

        break;

    default:
        echo "Comando não encontrado: {$comando}\n";
        exit(1);
}
