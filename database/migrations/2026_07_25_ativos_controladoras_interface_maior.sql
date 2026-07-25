-- Coluna 'interface' (prefixo do DeviceID -- PCI/USB/ACPI/...) estava
-- VARCHAR(30) e algumas maquinas mandam um DeviceID sem o backslash cedo
-- o suficiente, gerando um prefixo maior que isso. Isso estourava a coluna
-- com "Data too long", derrubando o checkin inteiro (Fatal error, sem
-- transacao) e disparando "Servidor respondeu sem mensagem (HTTP 500)" no
-- agente.

ALTER TABLE ativos_controladoras MODIFY interface VARCHAR(100) NULL;
