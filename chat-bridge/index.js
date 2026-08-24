/**
 * Chat interno -- Fase 2 (tempo real). Sidecar Node fora do ciclo de
 * vida do PHP, mesmo padrão já usado por whatsapp-bridge/index.js e
 * pelo MeshCentral neste projeto: roda como serviço systemd próprio,
 * instalado/gerenciado por scripts/system/chat_bridge_instalar_web.sh.
 *
 * O PHP nunca guarda socket nenhum aberto (Apache/mod_php não é feito
 * pra isso -- ver a decisão de arquitetura documentada na proposta do
 * módulo). Este processo só existe pra manter as conexões WebSocket dos
 * navegadores e empurrar eventos assim que o PHP avisa (POST /notificar,
 * chamado por App\Services\ChatBridgeService depois de já ter salvo a
 * mensagem no MySQL -- o WebSocket é só o empurrão em tempo real, nunca
 * a fonte da verdade). Se este processo cair, nada quebra: o polling que
 * já existia desde a Fase 1 continua entregando tudo em até poucos
 * segundos, só sem o "instantâneo".
 *
 * Autenticação em duas direções:
 *  - PHP -> aqui (POST /notificar, GET /status): X-Api-Key, igual o
 *    bridge do WhatsApp.
 *  - Navegador -> aqui (handshake do WebSocket): o navegador não sabe
 *    (e nunca deve saber) a X-Api-Key -- ele manda um token de uso
 *    único de 60s (?token=) que só o PHP emite pra sessão autenticada
 *    de quem está logado; este processo valida esse token chamando de
 *    volta um endpoint interno do PHP (GET /api/chat/validar-socket-token),
 *    autenticado com a MESMA X-Api-Key -- prova que quem está
 *    perguntando é este processo, não um chamador qualquer tentando
 *    adivinhar token de outra pessoa.
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import http from 'http';
import crypto from 'crypto';
import { WebSocketServer } from 'ws';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const CONFIG_PATH = path.join(__dirname, 'config.json');

if (!fs.existsSync(CONFIG_PATH)) {
    console.error('config.json não encontrado -- este processo não deve ser rodado fora do systemd criado por chat_bridge_instalar_web.sh.');
    process.exit(1);
}

const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
const PORTA = config.porta;
const API_KEY = String(config.apiKey || '');
const VALIDAR_TOKEN_URL = config.validarTokenUrl;

function chaveValida(chaveRecebida) {
    const a = Buffer.from(String(chaveRecebida));
    const b = Buffer.from(API_KEY);

    if (a.length !== b.length) {
        return false;
    }

    return crypto.timingSafeEqual(a, b);
}

function lerCorpo(req) {
    return new Promise((resolve, reject) => {
        let corpo = '';
        req.on('data', (chunk) => { corpo += chunk; });
        req.on('end', () => resolve(corpo));
        req.on('error', reject);
    });
}

// usuarioId (string) -> Set<WebSocket> -- um usuário pode ter mais de
// uma aba/dispositivo aberto ao mesmo tempo, todas recebem o evento.
const conexoesPorUsuario = new Map();

function registrarConexao(usuarioId, ws) {
    const chave = String(usuarioId);
    if (!conexoesPorUsuario.has(chave)) {
        conexoesPorUsuario.set(chave, new Set());
    }
    conexoesPorUsuario.get(chave).add(ws);
}

function removerConexao(usuarioId, ws) {
    const chave = String(usuarioId);
    const conjunto = conexoesPorUsuario.get(chave);
    if (!conjunto) {
        return;
    }
    conjunto.delete(ws);
    if (conjunto.size === 0) {
        conexoesPorUsuario.delete(chave);
    }
}

function totalConexoes() {
    let total = 0;
    for (const conjunto of conexoesPorUsuario.values()) {
        total += conjunto.size;
    }
    return total;
}

async function validarTokenNaPhp(token) {
    if (!token) {
        return null;
    }

    try {
        const resposta = await fetch(VALIDAR_TOKEN_URL + '?token=' + encodeURIComponent(token), {
            headers: { 'X-Api-Key': API_KEY },
        });

        if (!resposta.ok) {
            return null;
        }

        const dados = await resposta.json();

        return dados.success ? dados.usuarioId : null;
    } catch (e) {
        return null;
    }
}

const servidor = http.createServer(async (req, res) => {
    const chaveRecebida = req.headers['x-api-key'] || '';

    if (!chaveValida(chaveRecebida)) {
        res.writeHead(403, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, message: 'Chave inválida.' }));
        return;
    }

    const url = new URL(req.url, 'http://localhost');

    if (req.method === 'GET' && url.pathname === '/status') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true, conexoes: totalConexoes() }));
        return;
    }

    if (req.method === 'POST' && url.pathname === '/notificar') {
        const corpo = await lerCorpo(req);
        let payload;

        try {
            payload = JSON.parse(corpo);
        } catch (e) {
            res.writeHead(400, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: false, message: 'JSON inválido.' }));
            return;
        }

        const usuarioIds = Array.isArray(payload.usuarioIds) ? payload.usuarioIds : [];
        const mensagemParaEnviar = JSON.stringify({ evento: payload.evento, dados: payload.dados });

        let entregues = 0;
        for (const usuarioId of usuarioIds) {
            const conjunto = conexoesPorUsuario.get(String(usuarioId));
            if (!conjunto) {
                continue;
            }
            for (const ws of conjunto) {
                if (ws.readyState === ws.OPEN) {
                    ws.send(mensagemParaEnviar);
                    entregues++;
                }
            }
        }

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true, entregues }));
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, message: 'Rota não encontrada.' }));
});

const wss = new WebSocketServer({ noServer: true });

servidor.on('upgrade', async (req, socket, head) => {
    const url = new URL(req.url, 'http://localhost');
    const token = url.searchParams.get('token') || '';

    const usuarioId = await validarTokenNaPhp(token);

    if (!usuarioId) {
        socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
        socket.destroy();
        return;
    }

    wss.handleUpgrade(req, socket, head, (ws) => {
        registrarConexao(usuarioId, ws);

        ws.on('close', () => removerConexao(usuarioId, ws));
        ws.on('error', () => removerConexao(usuarioId, ws));
    });
});

// Bind só em 127.0.0.1 -- o navegador nunca fala com este processo
// diretamente, chega até aqui só via proxy reverso do Apache
// (mesma porta HTTPS/HTTP do site, ver chat_bridge_instalar_web.sh),
// mesmo raciocínio de segurança do bridge do WhatsApp (nunca exposto
// direto na rede, nenhuma regra de firewall nova precisa existir).
servidor.listen(PORTA, '127.0.0.1', () => {
    console.log(`Chat-bridge ouvindo em 127.0.0.1:${PORTA}`);
});
