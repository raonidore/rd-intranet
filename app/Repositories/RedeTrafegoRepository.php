<?php

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RedeTrafegoRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function registrarAmostra(string $interface, int $rxBytes, int $txBytes, int $rxPackets, int $txPackets): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO rede_trafego_historico (interface, rx_bytes, tx_bytes, rx_packets, tx_packets)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([$interface, $rxBytes, $txBytes, $rxPackets, $txPackets]);
    }

    /**
     * Agrega por dia/interface o menor e maior valor dos contadores
     * acumulados. O consumo do dia e a diferenca (max - min); se a maquina
     * reiniciar no meio do dia os contadores zeram e o valor fica subestimado
     * para aquele dia especifico, mas os dias seguintes voltam a ser exatos.
     */
    public function consumoPorDia(int $dias = 30): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                DATE(coletado_em) AS dia,
                interface,
                MIN(rx_bytes) AS rx_min, MAX(rx_bytes) AS rx_max,
                MIN(tx_bytes) AS tx_min, MAX(tx_bytes) AS tx_max,
                MIN(rx_packets) AS rx_packets_min, MAX(rx_packets) AS rx_packets_max,
                MIN(tx_packets) AS tx_packets_min, MAX(tx_packets) AS tx_packets_max
            FROM rede_trafego_historico
            WHERE coletado_em >= (CURDATE() - INTERVAL ? DAY)
            GROUP BY DATE(coletado_em), interface
            ORDER BY dia DESC, interface
        ");

        $stmt->execute([$dias]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalAmostras(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM rede_trafego_historico')->fetchColumn();
    }
}
