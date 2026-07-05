<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;

class SambaArquivosController extends Controller
{
    private const BASE_PATH    = '/srv/samba/Compartilhamentos';
    private const MAX_UPLOAD   = 100 * 1024 * 1024; // 100 MB
    private const EDITABLE_EXT = ['txt', 'csv', 'conf', 'cfg', 'ini', 'log', 'sh', 'md',
                                   'xml', 'json', 'html', 'css', 'js', 'php', 'py', 'sql'];

    // ── Validação de caminho ─────────────────────────────────────────────
    private function validarRel(string $rel): ?string
    {
        $rel = trim($rel, '/');

        if (str_contains($rel, '..') || str_contains($rel, "\0")) {
            return null;
        }

        return $rel;
    }

    private function scriptOutput(string $script, array $args): string
    {
        $cmd = 'sudo ' . escapeshellarg($script);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg($a);
        }
        return shell_exec($cmd . ' 2>/dev/null') ?? '';
    }

    private function jsonOutput(string $script, array $args): array
    {
        $out    = trim($this->scriptOutput($script, $args));
        $result = json_decode($out, true);
        return is_array($result) ? $result : ['success' => false, 'message' => 'Resposta inesperada.'];
    }

    // ── Listagem ─────────────────────────────────────────────────────────
    public function index(): void
    {
        AuthMiddleware::check();

        $rel = $this->validarRel($_GET['path'] ?? '');
        if ($rel === null) {
            $this->view('samba/arquivos', ['erro' => 'Caminho inválido.', 'arquivos' => [], 'pathAtual' => '', 'breadcrumb' => []]);
            return;
        }

        $raw  = trim($this->scriptOutput('/opt/rdtecnologia/scripts/lista_arquivos_samba_web.sh', [$rel]));
        $list = json_decode($raw, true);

        if (!is_array($list) || isset($list['error'])) {
            $this->view('samba/arquivos', [
                'erro'       => $list['error'] ?? 'Erro ao listar arquivos.',
                'arquivos'   => [],
                'pathAtual'  => $rel,
                'breadcrumb' => $this->breadcrumb($rel),
            ]);
            return;
        }

        $arquivos = array_map(function (array $item) use ($rel): array {
            $ext      = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
            $itemPath = $rel ? $rel . '/' . $item['name'] : $item['name'];

            return [
                'type'     => $item['type'],
                'name'     => $item['name'],
                'size'     => $item['size'],
                'modified' => $item['modified'] ? date('d/m/Y H:i', $item['modified']) : '-',
                'ext'      => $ext,
                'icon'     => $this->icon($item['type'], $ext),
                'editable' => $item['type'] === 'file' && in_array($ext, self::EDITABLE_EXT),
                'path'     => $itemPath,
            ];
        }, $list);

        $this->view('samba/arquivos', [
            'arquivos'   => $arquivos,
            'pathAtual'  => $rel,
            'breadcrumb' => $this->breadcrumb($rel),
            'erro'       => null,
        ]);
    }

    // ── Download ─────────────────────────────────────────────────────────
    public function download(): void
    {
        AuthMiddleware::check();

        $rel = $this->validarRel($_GET['path'] ?? '');
        if ($rel === null) {
            http_response_code(400); exit('Caminho inválido.');
        }

        $name = basename($rel);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        header('Cache-Control: no-cache');

        passthru('sudo /opt/rdtecnologia/scripts/ler_arquivo_samba_web.sh ' . escapeshellarg($rel) . ' 2>/dev/null');
        exit;
    }

    // ── Visualizar PDF ────────────────────────────────────────────────────
    public function visualizar(): void
    {
        AuthMiddleware::check();

        $rel = $this->validarRel($_GET['path'] ?? '');
        if ($rel === null) {
            http_response_code(400); exit('Caminho inválido.');
        }

        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            http_response_code(415); exit('Tipo de arquivo não suportado para visualização.');
        }

        $name = basename($rel);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
        header('Cache-Control: no-store');
        header('X-Frame-Options: SAMEORIGIN');

        passthru('sudo /opt/rdtecnologia/scripts/ler_arquivo_samba_web.sh ' . escapeshellarg($rel) . ' 2>/dev/null');
        exit;
    }

    // ── Ler conteúdo (para editor) ────────────────────────────────────────
    public function ler(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json');

        $rel = $this->validarRel($_GET['path'] ?? '');
        if ($rel === null) {
            echo json_encode(['success' => false, 'message' => 'Caminho inválido.']); return;
        }

        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EDITABLE_EXT)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não editável.']); return;
        }

        $content = $this->scriptOutput('/opt/rdtecnologia/scripts/ler_arquivo_samba_web.sh', [$rel]);
        echo json_encode(['success' => true, 'content' => $content]);
    }

    // ── Salvar texto editado ──────────────────────────────────────────────
    public function salvar(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json');

        $rel = $this->validarRel($_POST['path'] ?? '');
        if ($rel === null) {
            echo json_encode(['success' => false, 'message' => 'Caminho inválido.']); return;
        }

        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (!in_array($ext, self::EDITABLE_EXT)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não editável.']); return;
        }

        $tmpfile = tempnam('/tmp', 'samba_edit_');
        file_put_contents($tmpfile, $_POST['content'] ?? '');
        $result = $this->jsonOutput('/opt/rdtecnologia/scripts/salvar_arquivo_samba_web.sh', [$tmpfile, $rel]);
        @unlink($tmpfile);

        echo json_encode($result);
    }

    // ── Upload ────────────────────────────────────────────────────────────
    public function upload(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json');

        $rel = $this->validarRel($_POST['path'] ?? '');
        if ($rel === null) {
            echo json_encode(['success' => false, 'message' => 'Caminho inválido.']); return;
        }

        if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['arquivo']['error'] ?? -1;
            echo json_encode(['success' => false, 'message' => "Erro no upload (código $err)."]); return;
        }

        $file = $_FILES['arquivo'];

        if ($file['size'] > self::MAX_UPLOAD) {
            echo json_encode(['success' => false, 'message' => 'Arquivo muito grande (máx. 100 MB).']); return;
        }

        // Sanitizar nome mantendo acentos e espaços válidos
        $filename = preg_replace('/[<>:"\\/\\\\|?*\x00-\x1f]/', '_', $file['name']);
        $destRel  = $rel ? $rel . '/' . $filename : $filename;

        $result = $this->jsonOutput(
            '/opt/rdtecnologia/scripts/salvar_arquivo_samba_web.sh',
            [$file['tmp_name'], $destRel]
        );

        echo json_encode($result);
    }

    // ── Excluir ───────────────────────────────────────────────────────────
    public function excluir(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json');

        $rel = $this->validarRel($_POST['path'] ?? '');
        if ($rel === null) {
            echo json_encode(['success' => false, 'message' => 'Caminho inválido.']); return;
        }

        echo json_encode($this->jsonOutput('/opt/rdtecnologia/scripts/excluir_arquivo_samba_web.sh', [$rel]));
    }

    // ── Renomear ──────────────────────────────────────────────────────────
    public function renomear(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json');

        $rel      = $this->validarRel($_POST['path'] ?? '');
        $novoNome = trim($_POST['nome'] ?? '');

        if ($rel === null || $novoNome === '' || preg_match('/[<>:"\/\\\\|?*\x00-\x1f]/', $novoNome)) {
            echo json_encode(['success' => false, 'message' => 'Nome inválido.']); return;
        }

        echo json_encode($this->jsonOutput(
            '/opt/rdtecnologia/scripts/renomear_arquivo_samba_web.sh',
            [$rel, $novoNome]
        ));
    }

    // ── Criar pasta ───────────────────────────────────────────────────────
    public function criarPasta(): void
    {
        AuthMiddleware::check();
        header('Content-Type: application/json');

        $dirAtual = $this->validarRel($_POST['path'] ?? '');
        $nome     = trim($_POST['nome'] ?? '');

        if ($dirAtual === null || $nome === '' || preg_match('/[<>:"\\/\\\\|?*\x00-\x1f]/', $nome)) {
            echo json_encode(['success' => false, 'message' => 'Nome inválido.']); return;
        }

        $rel = $dirAtual ? $dirAtual . '/' . $nome : $nome;
        echo json_encode($this->jsonOutput('/opt/rdtecnologia/scripts/criar_pasta_samba_web.sh', [$rel]));
    }

    // ── Helpers ───────────────────────────────────────────────────────────
    private function icon(string $type, string $ext): string
    {
        if ($type === 'dir') {
            return 'bi-folder-fill text-warning';
        }

        return match($ext) {
            'pdf'                                    => 'bi-file-earmark-pdf text-danger',
            'jpg', 'jpeg', 'png', 'gif', 'bmp',
            'webp', 'svg', 'ico'                     => 'bi-file-earmark-image text-info',
            'doc', 'docx'                            => 'bi-file-earmark-word text-primary',
            'xls', 'xlsx'                            => 'bi-file-earmark-excel text-success',
            'csv'                                    => 'bi-file-earmark-spreadsheet text-success',
            'ppt', 'pptx'                            => 'bi-file-earmark-ppt text-orange',
            'zip', 'rar', '7z', 'tar', 'gz', 'bz2'  => 'bi-file-earmark-zip text-secondary',
            'txt', 'md', 'log'                       => 'bi-file-earmark-text',
            'conf', 'cfg', 'ini', 'xml', 'json'      => 'bi-file-earmark-code text-purple',
            'mp3', 'wav', 'ogg', 'flac', 'aac'       => 'bi-file-earmark-music text-secondary',
            'mp4', 'avi', 'mkv', 'mov', 'wmv'        => 'bi-file-earmark-play text-danger',
            default                                  => 'bi-file-earmark',
        };
    }

    private function breadcrumb(string $rel): array
    {
        $crumbs = [['name' => 'Compartilhamentos', 'path' => '']];
        if (empty($rel)) {
            return $crumbs;
        }

        $parts = explode('/', $rel);
        $accum = '';
        foreach ($parts as $part) {
            if ($part === '') continue;
            $accum    = $accum ? $accum . '/' . $part : $part;
            $crumbs[] = ['name' => $part, 'path' => $accum];
        }

        return $crumbs;
    }
}
