'use strict';

/**
 * Bridge local entre a RD Intranet (PHP) e o WhatsApp via QR Code
 * (Baileys) -- roda como serviço systemd próprio, instalado/gerenciado
 * por scripts/system/whatsapp_bridge_instalar_web.sh (mesmo padrão já
 * usado pelo MeshCentral neste projeto: Node.js fora do ciclo de vida
 * do PHP, falando por HTTP local).
 *
 * O PHP nunca fala o protocolo WhatsApp Web -- só chama esta API HTTP
 * local (GET /status, GET /qrcode, POST /enviar, POST /logout,
 * protegidos pela mesma chave em X-Api-Key) e recebe mensagens novas
 * via webhook (POST pro WEBHOOK_URL configurado, mesma chave).
 */

const fs = require('fs');
const path = require('path');
const http = require('http');
const crypto = require('crypto');
const pino = require('pino');
const QRCode = require('qrcode');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, jidDecode, isLidUser } = require('@whiskeysockets/baileys');

const CONFIG_PATH = path.join(__dirname, 'config.json');
const SESSAO_DIR = path.join(__dirname, 'sessao');

if (!fs.existsSync(CONFIG_PATH)) {
    console.error('config.json não encontrado -- este processo não deve ser rodado fora do systemd criado por whatsapp_bridge_instalar_web.sh.');
    process.exit(1);
}

const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
const PORTA = config.porta;
const API_KEY = String(config.apiKey || '');
const WEBHOOK_URL = config.webhookUrl;

const logger = pino({ level: 'error' });

let statusAtual = 'desconectado'; // desconectado | aguardando_qrcode | conectado
let qrcodeAtualDataUri = null;
let numeroConectado = null;
let socketAtual = null;

function chaveValida(chaveRecebida) {
    const a = Buffer.from(String(chaveRecebida));
    const b = Buffer.from(API_KEY);

    if (a.length !== b.length) {
        return false;
    }

    return crypto.timingSafeEqual(a, b);
}

async function avisarWebhook(payload) {
    try {
        const resposta = await fetch(WEBHOOK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Api-Key': API_KEY },
            body: JSON.stringify(payload),
        });

        if (!resposta.ok) {
            console.error('Webhook da RD Intranet respondeu status', resposta.status);
        }
    } catch (e) {
        console.error('Falha ao chamar o webhook da RD Intranet:', e.message);
    }
}

async function iniciarSessaoWhatsapp() {
    const { state, saveCreds } = await useMultiFileAuthState(SESSAO_DIR);

    const socket = makeWASocket({
        auth: state,
        logger,
        printQRInTerminal: false,
        syncFullHistory: false,
    });
    socketAtual = socket;

    socket.ev.on('creds.update', saveCreds);

    socket.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            statusAtual = 'aguardando_qrcode';
            qrcodeAtualDataUri = await QRCode.toDataURL(qr);
        }

        if (connection === 'open') {
            statusAtual = 'conectado';
            qrcodeAtualDataUri = null;
            numeroConectado = socket.user && socket.user.id ? socket.user.id.split(':')[0] : null;
        }

        if (connection === 'close') {
            statusAtual = 'desconectado';
            numeroConectado = null;

            const codigo = lastDisconnect && lastDisconnect.error && lastDisconnect.error.output
                ? lastDisconnect.error.output.statusCode
                : null;
            const deslogado = codigo === DisconnectReason.loggedOut;

            if (deslogado) {
                // Sessao invalidada de verdade (logout pelo celular) --
                // apaga credenciais locais, senao a proxima tentativa de
                // conectar fica girando em loop de erro usando uma sessao
                // morta em vez de gerar QR Code novo.
                fs.rmSync(SESSAO_DIR, { recursive: true, force: true });
            } else {
                // Queda de rede/restart do WhatsApp etc. -- reconecta
                // sozinho, reaproveitando a sessao existente.
                setTimeout(() => { iniciarSessaoWhatsapp().catch((e) => console.error(e)); }, 3000);
            }
        }
    });

    socket.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') {
            return;
        }

        for (const msg of messages) {
            if (!msg.message || msg.key.fromMe) {
                continue;
            }
            if (msg.key.remoteJid && msg.key.remoteJid.endsWith('@g.us')) {
                continue; // grupo -- fora de escopo por enquanto, só atendimento 1:1
            }

            // O WhatsApp vem escondendo o numero de telefone de varias contas
            // atras de um LID (identidade opaca, "...@lid") em vez do JID
            // classico ("numero@s.whatsapp.net") em remoteJid -- se for o
            // caso, o numero de telefone de verdade vem à parte em
            // key.senderPn. Sem isso, a gente salvava os digitos do LID como
            // se fossem o numero do contato, e a resposta que mandavamos
            // depois pra "<digitos do lid>@s.whatsapp.net" nao chegava em
            // lugar nenhum (nao existe conta nenhuma nesse endereco).
            const jidOrigem = isLidUser(msg.key.remoteJid) && msg.key.senderPn
                ? msg.key.senderPn
                : msg.key.remoteJid;
            const numero = jidDecode(jidOrigem)?.user || '';

            if (!numero) {
                continue;
            }

            const nome = msg.pushName || null;
            const texto = msg.message.conversation
                || (msg.message.extendedTextMessage && msg.message.extendedTextMessage.text)
                || '';

            if (!texto) {
                continue; // midia (imagem/audio/documento) fica pra uma proxima fase
            }

            await avisarWebhook({
                numero,
                nome,
                texto,
                tipo: 'texto',
                id_mensagem: msg.key.id,
            });
        }
    });

    return socket;
}

function lerCorpo(req) {
    return new Promise((resolve, reject) => {
        let corpo = '';
        req.on('data', (chunk) => { corpo += chunk; });
        req.on('end', () => resolve(corpo));
        req.on('error', reject);
    });
}

const servidor = http.createServer(async (req, res) => {
    const chaveRecebida = req.headers['x-api-key'] || '';

    if (!chaveValida(chaveRecebida)) {
        res.writeHead(403, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: false, message: 'Chave inválida.' }));
        return;
    }

    if (req.method === 'GET' && req.url === '/status') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true, status: statusAtual, numero: numeroConectado }));
        return;
    }

    if (req.method === 'GET' && req.url === '/qrcode') {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify(
            qrcodeAtualDataUri
                ? { success: true, qrcode: qrcodeAtualDataUri }
                : { success: false, message: 'Nenhum QR Code disponível no momento.' }
        ));
        return;
    }

    if (req.method === 'POST' && req.url === '/enviar') {
        try {
            const dados = JSON.parse((await lerCorpo(req)) || '{}');

            if (!socketAtual || statusAtual !== 'conectado') {
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: false, message: 'WhatsApp não está conectado.' }));
                return;
            }

            const jid = String(dados.numero || '').replace(/\D+/g, '') + '@s.whatsapp.net';
            await socketAtual.sendMessage(jid, { text: String(dados.texto || '') });

            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: true, message: 'Mensagem enviada.' }));
        } catch (e) {
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ success: false, message: 'Falha ao enviar: ' + e.message }));
        }
        return;
    }

    if (req.method === 'POST' && req.url === '/logout') {
        try {
            if (socketAtual) {
                await socketAtual.logout();
            }
        } catch (e) {
            // segue o fluxo mesmo se o logout der erro (sessao ja pode ter caido sozinha)
        }

        fs.rmSync(SESSAO_DIR, { recursive: true, force: true });
        statusAtual = 'desconectado';
        numeroConectado = null;
        qrcodeAtualDataUri = null;

        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ success: true, message: 'Desconectado.' }));
        return;
    }

    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, message: 'Rota não encontrada.' }));
});

servidor.listen(PORTA, '127.0.0.1', () => {
    console.log(`Bridge WhatsApp ouvindo em 127.0.0.1:${PORTA}`);
});

iniciarSessaoWhatsapp().catch((e) => {
    console.error('Falha ao iniciar sessão WhatsApp:', e);
});
