<?php
/**
 * Editor de texto com formatação (descrição da tarefa). Espera $nomeCampo,
 * $valorHtml (já passado por TextoRicoService::paraHtml) e opcionalmente
 * $placeholderEditor / $alturaEditor. O HTML vai num campo escondido no envio
 * e é limpo de novo no servidor (TextoRicoService::sanitizar).
 */
$coresTexto = ['#1f2328' => 'Preto', '#dc3545' => 'Vermelho', '#fd7e14' => 'Laranja', '#b58100' => 'Amarelo escuro', '#198754' => 'Verde', '#0d6efd' => 'Azul', '#6f42c1' => 'Roxo', '#6c757d' => 'Cinza'];
$coresMarca = ['#fff3cd' => 'Amarelo', '#d1e7dd' => 'Verde', '#cfe2ff' => 'Azul', '#f8d7da' => 'Vermelho', '#e2d9f3' => 'Roxo'];
?>
<div class="editor-rico position-relative" data-obrigatorio="<?= !empty($editorObrigatorio) ? '1' : '0' ?>" data-marcaveis="<?= htmlspecialchars(json_encode($marcaveisEditor ?? [], JSON_UNESCAPED_UNICODE)) ?>">
    <div class="editor-rico-barra" role="toolbar" aria-label="Formatação do texto">
        <button type="button" class="btn btn-sm btn-light" data-comando="bold" title="Negrito (Ctrl+B)"><i class="bi bi-type-bold"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="italic" title="Itálico (Ctrl+I)"><i class="bi bi-type-italic"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="underline" title="Sublinhado (Ctrl+U)"><i class="bi bi-type-underline"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="strikeThrough" title="Riscado"><i class="bi bi-type-strikethrough"></i></button>
        <span class="editor-rico-sep"></span>
        <span class="dropdown">
            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" title="Cor do texto"><i class="bi bi-fonts" style="border-bottom:3px solid #dc3545"></i></button>
            <div class="dropdown-menu p-2 editor-rico-paleta">
                <div class="small text-muted mb-1">Cor do texto</div>
                <?php foreach ($coresTexto as $hex => $nome): ?>
                    <button type="button" class="editor-rico-cor" data-comando="foreColor" data-valor="<?= $hex ?>" title="<?= $nome ?>" style="background:<?= $hex ?>"></button>
                <?php endforeach; ?>
                <label class="editor-rico-cor editor-rico-cor-livre" title="Outra cor"><i class="bi bi-plus"></i><input type="color" data-comando="foreColor" value="#0d6efd"></label>
            </div>
        </span>
        <span class="dropdown">
            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" title="Marca-texto"><i class="bi bi-highlighter"></i></button>
            <div class="dropdown-menu p-2 editor-rico-paleta">
                <div class="small text-muted mb-1">Marca-texto</div>
                <?php foreach ($coresMarca as $hex => $nome): ?>
                    <button type="button" class="editor-rico-cor" data-comando="hiliteColor" data-valor="<?= $hex ?>" title="<?= $nome ?>" style="background:<?= $hex ?>"></button>
                <?php endforeach; ?>
                <button type="button" class="editor-rico-cor editor-rico-cor-livre" data-comando="hiliteColor" data-valor="#ffffff" title="Sem marca-texto"><i class="bi bi-slash-circle"></i></button>
            </div>
        </span>
        <span class="editor-rico-sep"></span>
        <span class="dropdown">
            <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" title="Tamanho da fonte"><i class="bi bi-type"></i><i class="bi bi-caret-down-fill" style="font-size:9px"></i></button>
            <div class="dropdown-menu py-1">
                <?php foreach ([12 => 'Pequeno', 14 => 'Normal', 16 => 'Médio', 20 => 'Grande', 24 => 'Muito grande', 32 => 'Enorme'] as $px => $nome): ?>
                    <button type="button" class="dropdown-item py-1" data-comando="tamanhoFonte" data-valor="<?= $px ?>" style="font-size:<?= $px ?>px; line-height:1.2"><?= $nome ?></button>
                <?php endforeach; ?>
            </div>
        </span>
        <button type="button" class="btn btn-sm btn-light" data-comando="formatBlock" data-valor="h5" title="Título"><i class="bi bi-type-h3"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="formatBlock" data-valor="div" title="Texto normal"><i class="bi bi-paragraph"></i></button>
        <span class="editor-rico-sep"></span>
        <button type="button" class="btn btn-sm btn-light" data-comando="insertUnorderedList" title="Lista com marcadores"><i class="bi bi-list-ul"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="insertOrderedList" title="Lista numerada (1, 2, 3...)"><i class="bi bi-list-ol"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="indent" title="Aumentar recuo"><i class="bi bi-text-indent-left"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="outdent" title="Diminuir recuo"><i class="bi bi-text-indent-right"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="insertHorizontalRule" title="Linha separadora"><i class="bi bi-hr"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="createLink" title="Transformar a seleção em link"><i class="bi bi-link-45deg"></i></button>
        <button type="button" class="btn btn-sm btn-light" data-comando="removeFormat" title="Limpar formatação da seleção"><i class="bi bi-eraser"></i></button>
    </div>
    <div class="form-control form-control-sm editor-rico-area" contenteditable="true" spellcheck="true"
         style="min-height:<?= (int)($alturaEditor ?? 90) ?>px"
         data-placeholder="<?= htmlspecialchars($placeholderEditor ?? 'Descrição') ?>"><?= $valorHtml ?></div>
    <div class="list-group position-absolute shadow-sm d-none editor-rico-mencoes" style="z-index:40; min-width:240px; max-height:200px; overflow-y:auto"></div>
    <input type="hidden" name="<?= htmlspecialchars($nomeCampo) ?>" class="editor-rico-campo">
    <div class="form-text mt-0">Selecione um trecho e use a barra acima. Ctrl+clique abre um link.<?= !empty($marcaveisEditor) ? (!empty($editorObrigatorio) ? ' Use @ para mencionar alguém (avisado ao enviar).' : ' Use @ para mencionar alguém da tarefa (avisado ao salvar).') : '' ?></div>
</div>
