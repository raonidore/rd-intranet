<?php

namespace App\Services;

/**
 * Catalogo de acoes pontuais que exigem root/SSH e que o pipeline de
 * "Atualizar agora" nao consegue rodar sozinho (o proprio www-data nao
 * tem privilegio pra se autoconceder mais acesso -- ver comentario em
 * scripts/grant-sudo-wildcard.sh). Cada entrada pode opcionalmente se
 * autoverificar (campo "verificar"); quando nao da pra verificar sozinho,
 * a tela de Administracao > Atualizacoes pede confirmacao manual do
 * admin, guardada em passos_manuais_confirmacoes.
 *
 * Pra adicionar um passo novo no futuro (ex: uma feature que precise de
 * outro ajuste unico de root), so acrescente um item aqui.
 */
class PassoManualCatalogo
{
    public static function itens(): array
    {
        return [
            [
                'chave' => 'grant_sudo_wildcard',
                'titulo' => 'Liberar regra coringa de sudo para os scripts da RD Intranet',
                'descricao' => 'Sem essa regra, cada script novo publicado em /opt/rdtecnologia/scripts precisa ser liberado manualmente no sudoers antes de funcionar pela tela web (ex: aconteceu com os scripts do módulo Antivírus). Rodar uma vez resolve para qualquer script atual ou futuro.',
                'comando' => 'sudo /var/www/rd.intranet/scripts/grant-sudo-wildcard.sh',
                'verificar' => function (): ?bool {
                    $resultado = (new LinuxService())->executarScript('/opt/rdtecnologia/scripts/sudo_probe_web.sh');
                    return $resultado['success'] && trim($resultado['output']) === 'OK';
                },
            ],
        ];
    }
}
