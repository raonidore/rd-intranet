<?php

use App\Components\Alert;

ob_start();
?>

<div class="mb-4">
    <h4 class="mb-1"><i class="bi bi-shield-lock me-1"></i> Política de Senha</h4>
    <small class="text-muted">Regras de complexidade exigidas ao criar ou trocar qualquer senha no sistema (inclusive a sua).</small>
</div>

<?= Alert::flash() ?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="post" action="<?= url('/administracao/usuarios/politica-senha') ?>">
                    <div class="mb-3">
                        <label class="form-label">Comprimento mínimo</label>
                        <input type="number" name="comprimento_minimo" class="form-control" style="max-width:120px"
                               min="4" max="64" required value="<?= (int)$politica['comprimento_minimo'] ?>">
                        <small class="text-muted">Entre 4 e 64 caracteres.</small>
                    </div>

                    <div class="form-check mb-2">
                        <input type="checkbox" name="maiuscula_minuscula" value="1" class="form-check-input" id="ppMaiuscula" <?= $politica['maiuscula_minuscula'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ppMaiuscula">Exigir mistura de letras maiúsculas (A-Z) e minúsculas (a-z)</label>
                    </div>

                    <div class="form-check mb-2">
                        <input type="checkbox" name="numero" value="1" class="form-check-input" id="ppNumero" <?= $politica['numero'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ppNumero">Exigir presença de números (0-9)</label>
                    </div>

                    <div class="form-check mb-2">
                        <input type="checkbox" name="especial" value="1" class="form-check-input" id="ppEspecial" <?= $politica['especial'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ppEspecial">Exigir caracteres especiais ou símbolos (!, @, #, $, etc.)</label>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="dados_obvios" value="1" class="form-check-input" id="ppObvios" <?= $politica['dados_obvios'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ppObvios">
                            Bloquear dados óbvios (nome, login, e-mail do usuário ou sequências simples como "123456")
                        </label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('/administracao/usuarios') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Voltar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg"></i> Salvar política
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$conteudo = ob_get_clean();
$titulo = 'Política de Senha';

require __DIR__ . '/../layouts/main.php';
