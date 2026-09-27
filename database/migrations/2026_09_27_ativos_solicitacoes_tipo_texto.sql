-- O tipo da solicitação era ENUM com os 5 valores originais; os tipos da
-- aba Network (network_list, network_apply, network_revert, network_ping,
-- network_speedtest) eram recusados pelo banco e a tela recebia resposta
-- vazia ("JSON.parse: unexpected end of data"). Os tipos aceitos já são
-- validados em AtivoService::TIPOS_SOLICITACAO_VALIDOS -- texto aqui evita
-- repetir o problema a cada tipo novo.
ALTER TABLE ativos_solicitacoes MODIFY COLUMN tipo VARCHAR(40) NOT NULL;
