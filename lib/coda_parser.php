<?php
// lib/coda_parser.php — Parser de fichiers CODA belges (COded DAta)
// Format CODA v2 — standard Isabel/Belfius/BNP/ING/KBC

class CodaParser {

    private $lines = array();
    private $transactions = array();
    private $header = array();
    private $errors = array();

    // ── Charger et parser un fichier CODA ────────────────────────────────
    public function parse($content) {
        $this->lines        = explode("\n", str_replace("\r", "", $content));
        $this->transactions = array();
        $this->header       = array();
        $this->errors       = array();

        $current_tx = null;

        foreach ($this->lines as $line_num => $line) {
            if (strlen($line) < 1) continue;

            $record_type = substr($line, 0, 1);

            switch ($record_type) {
                case '0': // En-tête
                    $this->header = $this->parseHeader($line);
                    break;

                case '1': // Identification du compte
                    // Nouveau compte
                    break;

                case '2': // Transaction (mouvement)
                    $seq = substr($line, 1, 1); // 0=simple, 1=première ligne, 2=continuation

                    if ($seq === '1' || $seq === '0') {
                        // Sauvegarder la transaction précédente
                        if ($current_tx !== null) {
                            $this->transactions[] = $current_tx;
                        }
                        $current_tx = $this->parseTransaction($line);
                    } elseif ($seq === '2' && $current_tx !== null) {
                        // Continuation — enrichir avec info communication
                        $this->parseTransactionContinuation($line, $current_tx);
                    }
                    break;

                case '3': // Information complémentaire
                    if ($current_tx !== null) {
                        $this->parseInfoLine($line, $current_tx);
                    }
                    break;

                case '8': // Solde final
                    // Optionnel — on peut ignorer
                    break;

                case '9': // Fin de fichier
                    if ($current_tx !== null) {
                        $this->transactions[] = $current_tx;
                        $current_tx = null;
                    }
                    break;
            }
        }

        // Ne pas oublier la dernière transaction
        if ($current_tx !== null) {
            $this->transactions[] = $current_tx;
        }

        return $this->transactions;
    }

    // ── Parser l'en-tête (record type 0) ────────────────────────────────
    private function parseHeader($line) {
        return array(
            'creation_date' => $this->parseDate(substr($line, 5, 6)),
            'bank_id'       => trim(substr($line, 11, 3)),
            'duplicate'     => substr($line, 16, 1) === 'D',
            'reference'     => trim(substr($line, 24, 10)),
            'addressee'     => trim(substr($line, 34, 26)),
            'bic'           => trim(substr($line, 60, 11)),
            'account_holder'=> trim(substr($line, 71, 26)),
        );
    }

    // ── Parser une transaction (record type 2, séquence 1) ───────────────
    private function parseTransaction($line) {
        // Signe du montant
        $amount_str = substr($line, 32, 15);
        $sign       = substr($line, 31, 1); // 0=crédit, 1=débit
        $amount     = intval($amount_str) / 1000; // Centimes → euros (3 décimales)
        if ($sign === '1') $amount = -$amount;

        // Date valeur
        $date_str = substr($line, 47, 6);
        $date     = $this->parseDate($date_str);

        // Communication
        $comm_type  = substr($line, 53, 1); // 0=libre, 1=structurée (OGM)
        $comm       = trim(substr($line, 54, 35));

        // Formater la communication structurée en +++
        $ogm = null;
        if ($comm_type === '1') {
            $ogm = $this->formatOGM($comm);
        } else {
            // Chercher un OGM dans la communication libre
            $ogm = $this->extractOGM($comm);
        }

        // Compte de contrepartie
        $account_raw = trim(substr($line, 89, 37));

        return array(
            'sequence'       => trim(substr($line, 2, 4)),
            'date'           => $date,
            'amount'         => $amount,
            'credit'         => $sign === '0',
            'comm_type'      => $comm_type,
            'communication'  => $comm,
            'ogm'            => $ogm,
            'counterpart'    => $account_raw,
            'name'           => '',
            'extra_info'     => '',
            'matched_member' => null,
        );
    }

    // ── Parser la suite d'une transaction (record type 2, séquence 2) ───
    private function parseTransactionContinuation($line, &$tx) {
        $comm_part = trim(substr($line, 10, 35));
        if (!empty($comm_part)) {
            $tx['communication'] .= ' ' . $comm_part;
            // Re-chercher OGM dans la communication complète
            if ($tx['ogm'] === null) {
                $tx['ogm'] = $this->extractOGM($tx['communication']);
            }
        }
        // Nom du donneur
        $name_part = trim(substr($line, 45, 35));
        if (!empty($name_part)) {
            $tx['name'] = $name_part;
        }
    }

    // ── Parser ligne d'info (record type 3) ─────────────────────────────
    private function parseInfoLine($line, &$tx) {
        $info = trim(substr($line, 10, 35));
        if (!empty($info)) {
            $tx['extra_info'] .= ' ' . $info;
        }
    }

    // ── Formatter un OGM depuis code CODA (12 chiffres → +++) ───────────
    private function formatOGM($code) {
        $digits = preg_replace('/[^0-9]/', '', $code);
        if (strlen($digits) < 12) return null;
        $d = str_pad($digits, 12, '0', STR_PAD_LEFT);
        return '+++' . substr($d, 0, 3) . '/' . substr($d, 3, 4) . '/' . substr($d, 7, 5) . '+++';
    }

    // ── Extraire OGM d'un texte libre ────────────────────────────────────
    private function extractOGM($text) {
        // Format +++ XXX/XXXX/XXXXX +++
        if (preg_match('/\+\+\+\s*(\d{3})\s*\/\s*(\d{4})\s*\/\s*(\d{5})\s*\+\+\+/', $text, $m)) {
            return '+++' . $m[1] . '/' . $m[2] . '/' . $m[3] . '+++';
        }
        // Format *** XXX/XXXX/XXXXX ***
        if (preg_match('/\*\*\*\s*(\d{3})\s*\/\s*(\d{4})\s*\/\s*(\d{5})\s*\*\*\*/', $text, $m)) {
            return '+++' . $m[1] . '/' . $m[2] . '/' . $m[3] . '+++';
        }
        return null;
    }

    // ── Parser une date CODA (JJMMAA → YYYY-MM-DD) ──────────────────────
    private function parseDate($str) {
        if (strlen($str) < 6) return null;
        $day   = substr($str, 0, 2);
        $month = substr($str, 2, 2);
        $year  = substr($str, 4, 2);
        $year_full = (intval($year) > 50) ? '19' . $year : '20' . $year;
        return $year_full . '-' . $month . '-' . $day;
    }

    // ── Getters ──────────────────────────────────────────────────────────
    public function getTransactions() { return $this->transactions; }
    public function getHeader()       { return $this->header; }
    public function getErrors()       { return $this->errors; }

    // ── Filtrer uniquement les crédits (dons reçus) ──────────────────────
    public function getCredits() {
        return array_filter($this->transactions, function($tx) {
            return $tx['credit'] && $tx['amount'] > 0;
        });
    }
}

// ════════════════════════════════════════════════════════════════════════════
// CSV Belfius Parser — Export CSV depuis Belfius Direct Net
// Format : Date;Numéro d'extrait;Numéro de transaction;Compte;Nom du compte;
//          Contrepartie;Nom de la contrepartie;Rue et numéro;Code postal et localité;
//          Transaction;Date valeur;Montant;Devise;BIC;Code pays;Communications
// ════════════════════════════════════════════════════════════════════════════
class BelfiusCsvParser {

    private $transactions = array();
    private $errors       = array();

    public function parse($content) {
        $this->transactions = array();
        $this->errors       = array();

        // Détecter le séparateur (Belfius utilise ; ou ,)
        $firstLine = strtok($content, "\n");
        $sep = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        // Supprimer BOM UTF-8 si présent
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = explode("\n", str_replace("\r", "", $content));

        // Trouver la ligne d'en-tête
        $headers = array();
        $dataStart = 0;
        foreach ($lines as $i => $line) {
            if (empty(trim($line))) continue;
            $cols = str_getcsv($line, $sep, '"');
            // Détecter la ligne d'en-tête (contient "Date" ou "Montant")
            $lineStr = strtolower($line);
            if (strpos($lineStr, 'montant') !== false || strpos($lineStr, 'amount') !== false ||
                strpos($lineStr, 'date') !== false) {
                $headers   = array_map('trim', $cols);
                $dataStart = $i + 1;
                break;
            }
        }

        if (empty($headers)) {
            $this->errors[] = 'Impossible de trouver les en-têtes CSV Belfius.';
            return $this->transactions;
        }

        // Mapper les colonnes (Belfius FR et EN)
        $colMap = array(
            'date'          => ['Date', 'Date de comptabilisation', 'Booking date'],
            'valeur'        => ['Date valeur', 'Value date', 'Date de valeur'],
            'montant'       => ['Montant', 'Amount'],
            'devise'        => ['Devise', 'Currency'],
            'contrepartie'  => ['Contrepartie', 'Counterpart', 'Contre-partie'],
            'nom_contre'    => ['Nom de la contrepartie', 'Name of the counterpart', 'Nom contrepartie'],
            'communication' => ['Communications', 'Communication', 'Motif'],
            'type'          => ['Transaction', 'Type'],
            'extrait'       => ["Numéro d'extrait", 'Statement number'],
        );

        $idx = array();
        foreach ($colMap as $key => $candidates) {
            foreach ($candidates as $candidate) {
                $pos = array_search($candidate, $headers);
                if ($pos !== false) { $idx[$key] = $pos; break; }
            }
        }

        // Parser chaque ligne de données
        for ($i = $dataStart; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            $cols = str_getcsv($line, $sep, '"');
            if (count($cols) < 3) continue;

            $get = function($key) use ($cols, $idx) {
                return isset($idx[$key]) && isset($cols[$idx[$key]])
                    ? trim($cols[$idx[$key]]) : '';
            };

            // Montant
            $montantRaw = $get('montant');
            $montantRaw = str_replace(' ', '', $montantRaw);   // espaces milliers
            $montantRaw = str_replace(',', '.', $montantRaw);  // virgule → point
            $amount = floatval($montantRaw);

            if ($amount == 0) continue; // ignorer les lignes vides

            // Communication
            $comm = $get('communication');

            // Extraire OGM de la communication
            $ogm = null;
            if (preg_match('/\+{3}\s*(\d{3})\s*\/\s*(\d{4})\s*\/\s*(\d{5})\s*\+{3}/', $comm, $m)) {
                $ogm = '+++' . $m[1] . '/' . $m[2] . '/' . $m[3] . '+++';
            } elseif (preg_match('/(\d{3})\/?(\d{4})\/?(\d{5})/', $comm, $m)) {
                // OGM sans les +++ (12 chiffres consécutifs)
                $raw12 = $m[1] . $m[2] . $m[3];
                if (strlen($raw12) === 12) {
                    $ogm = '+++' . $m[1] . '/' . $m[2] . '/' . $m[3] . '+++';
                }
            }

            // Date
            $dateRaw = $get('date');
            $date = null;
            // Formats Belfius : DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD
            if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/', $dateRaw, $m)) {
                $date = $m[3] . '-' . $m[2] . '-' . $m[1];
            } elseif (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $dateRaw, $m)) {
                $date = $dateRaw;
            }

            // IBAN contrepartie
            $contre = strtoupper(preg_replace('/\s+/', '', $get('contrepartie')));
            $ibanContre = preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $contre) ? $contre : null;

            $this->transactions[] = array(
                'date'           => $date,
                'date_valeur'    => $get('valeur'),
                'amount'         => $amount,
                'credit'         => $amount > 0,
                'communication'  => $comm,
                'ogm'            => $ogm,
                'counterpart'    => $ibanContre,
                'nom_contre'     => $get('nom_contre'),
                'type'           => $get('type'),
                'source'         => 'csv_belfius',
            );
        }

        return $this->transactions;
    }

    public function getCredits() {
        return array_values(array_filter($this->transactions, function($tx) {
            return $tx['credit'] && $tx['amount'] > 0;
        }));
    }

    public function getTransactions() { return $this->transactions; }
    public function getErrors()       { return $this->errors; }
}
