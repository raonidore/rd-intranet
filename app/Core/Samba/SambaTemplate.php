<?php

namespace App\Core\Samba;

use App\Services\ExtensaoPerigosaService;

class SambaTemplate
{
    public static function global(): string
    {
        return <<<CONF
[global]
   workgroup = WORKGROUP
   server string = RD Intranet
   security = user

   map to guest = Never

   log file = /var/log/samba/%m.log
   max log size = 10000
   logging = file

   server min protocol = SMB2
   client min protocol = SMB2

   load printers = no
   disable spoolss = yes
   printing = bsd
   printcap name = /dev/null

   vfs objects = acl_xattr recycle

   map acl inherit = yes
   store dos attributes = yes

   access based share enum = yes
   hide unreadable = yes

   recycle:repository = .recycle
   recycle:keeptree = yes
   recycle:versions = yes
   recycle:touch = yes

   unix charset = UTF-8
   dos charset = CP850

   follow symlinks = no
   wide links = no

CONF;
    }

    public static function share(array $share, array $usuariosAutorizados = []): string
    {
        $nome = $share['nome'];
        $path = $share['caminho'];
        $grupo = $share['grupo'] ?? $share['grupo_linux'] ?? '';
        $readOnly = ((int)($share['somente_leitura'] ?? 0) === 1) ? 'yes' : 'no';

        // svc_acl_admin precisa conseguir conectar em todo compartilhamento para
        // gerenciar a ACL via smbcacls (SeDiskOperatorPrivilege não dispensa
        // "valid users" -- é uma checagem separada, feita antes do tree-connect).
        $validUsers = trim("@{$grupo} @ti svc_acl_admin " . implode(' ', $usuariosAutorizados));

        $config = <<<CONF

[$nome]
   path = $path
   browseable = yes
   read only = $readOnly

   valid users = $validUsers
   force group = $grupo

   admin users = svc_acl_admin

   create mask = 0660
   directory mask = 2770

   hide unreadable = yes
   hide unwriteable files = yes

CONF;

        if ((int)($share['bloqueio_extensoes'] ?? 0) === 1) {
            // lista vem do catalogo editavel em Deploy Center -- "Extensoes
            // perigosas" (ExtensaoPerigosaService), nao mais fixa aqui. Cada
            // extensao ja passou por sanitizacao alfanumerica no momento de
            // salvar (ExtensaoPerigosaService::salvar()), entao e seguro
            // interpolar direto na linha "veto files" (formato do Samba:
            // padroes separados e delimitados por "/").
            $extensoesAtivas = ExtensaoPerigosaService::listarAtivas();

            if (!empty($extensoesAtivas)) {
                $vetoFiles = '/' . implode('/', array_map(fn(string $ext) => "*.{$ext}", $extensoesAtivas)) . '/';

                $config .= "   veto files = {$vetoFiles}\n"
                    . "   delete veto files = yes\n\n";
            }
        }

        return $config;
    }
}
