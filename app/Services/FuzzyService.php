<?php

namespace App\Services;

class FuzzyService
{
    /**
     * Menghitung Tingkat Risiko Parkir (%) berdasarkan Jarak Belakang & Samping
     * Sesuai PROJECT SPECIFICATION & ARCHITECTURE.md §2
     */
    public function evaluate(float $rearDist, float $sideDist): array
    {
        // 1. Fuzzifikasi Jarak Belakang (0 - 18.5 cm)
        $rearSangatDekat = $this->membershipSangatDekat($rearDist);
        $rearSedang      = $this->membershipSedang($rearDist);
        $rearJauh        = $this->membershipJauh($rearDist);

        // 2. Fuzzifikasi Jarak Samping (0 - 11.0 cm)
        $sideMiringKiri  = $this->membershipMiringKiri($sideDist);
        $sideIdeal       = $this->membershipIdeal($sideDist);
        $sideMiringKanan = $this->membershipMiringKanan($sideDist);

        // 3. Evaluasi 9 Aturan Fuzzy & Inferensi (Metode Tsukamoto / Weighted Average)
        $rules = [
            // [ alpha, z_output_risk ]
            [$this->min($rearSangatDekat, $sideMiringKiri), 95],  // Sangat Dekat + Miring Kiri -> Tinggi
            [$this->min($rearSangatDekat, $sideIdeal), 85],       // Sangat Dekat + Ideal Tengah -> Tinggi
            [$this->min($rearSangatDekat, $sideMiringKanan), 95], // Sangat Dekat + Miring Kanan -> Tinggi
            [$this->min($rearSedang, $sideMiringKiri), 60],       // Sedang + Miring Kiri -> Sedang
            [$this->min($rearSedang, $sideIdeal), 30],          // Sedang + Ideal Tengah -> Rendah
            [$this->min($rearSedang, $sideMiringKanan), 60],      // Sedang + Miring Kanan -> Sedang
            [$this->min($rearJauh, $sideMiringKiri), 45],         // Jauh + Miring Kiri -> Sedang
            [$this->min($rearJauh, $sideIdeal), 10],            // Jauh + Ideal Tengah -> Rendah
            [$this->min($rearJauh, $sideMiringKanan), 45],        // Jauh + Miring Kanan -> Sedang
        ];

        $numerator = 0;
        $denominator = 0;

        foreach ($rules as [$alpha, $z]) {
            if ($alpha > 0) {
                $numerator += $alpha * $z;
                $denominator += $alpha;
            }
        }

        $riskScore = $denominator > 0 ? ($numerator / $denominator) : 0;
        $riskScore = round(min(100.0, max(0.0, $riskScore)), 1);

        // Tentukan Status Fuzzy
        $status = 'AMAN';
        if ($riskScore >= 65) {
            $status = 'BAHAYA';
        } elseif ($riskScore >= 30) {
            $status = 'WASPADA';
        }

        return [
            'fuzzy_risk_score' => $riskScore,
            'fuzzy_status' => $status,
        ];
    }

    private function membershipSangatDekat(float $x): float
    {
        if ($x <= 3) return 1.0;
        if ($x >= 6) return 0.0;
        return (6 - $x) / (6 - 3);
    }

    private function membershipSedang(float $x): float
    {
        if ($x <= 4 || $x >= 12) return 0.0;
        if ($x <= 8) return ($x - 4) / (8 - 4);
        return (12 - $x) / (12 - 8);
    }

    private function membershipJauh(float $x): float
    {
        if ($x <= 9) return 0.0;
        if ($x >= 14) return 1.0;
        return ($x - 9) / (14 - 9);
    }

    private function membershipMiringKiri(float $x): float
    {
        if ($x <= 3.5) return 1.0;
        if ($x >= 5.5) return 0.0;
        return (5.5 - $x) / (5.5 - 3.5);
    }

    private function membershipIdeal(float $x): float
    {
        if ($x <= 3.0 || $x >= 7.5) return 0.0;
        if ($x <= 5.5) return ($x - 3.0) / (5.5 - 3.0);
        return (7.5 - $x) / (7.5 - 5.5);
    }

    private function membershipMiringKanan(float $x): float
    {
        if ($x <= 5.5) return 0.0;
        if ($x >= 7.0) return 1.0;
        return ($x - 5.5) / (7.0 - 5.5);
    }

    private function min(float $a, float $b): float
    {
        return $a < $b ? $a : $b;
    }
}
