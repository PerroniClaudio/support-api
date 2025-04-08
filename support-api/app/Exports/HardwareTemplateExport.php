<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class HardwareTemplateExport implements FromArray
{
    public function __construct()
    {
    }
    
    // NON CAMBIARE L'ORDINE DEGLI ELEMENTI NELL'ARRAY. (Se si deve modificare allora va aggiornato anche in HardwareImport)
    public function array(): array {
        $template_data = [];
        $headers = [
            "Marca *",
            "Modello *",
            "Seriale *",
            "Tipo (testo, preso dalla lista nel gestionale)",
            "Data d'acquisto (gg/mm/aaaa)",
            "Proprietà (testo, preso tra le opzioni nel gestionale)",
            "Specificare (se proprietà è Altro)",
            "Cespite aziendale (compilare almeno uno tra cespite aziendale e etichetta)",
            "Etichetta (compilare almeno uno tra cespite aziendale e etichetta)",
            "Note",
            "Uso esclusivo (Si/No, Se manca viene impostato su No)",
            "ID Azienda",
            "ID utenti (separati da virgola)",
            "ID utente responsabile dell'assegnazione (deve essere admin o del supporto)"
        ];
        
        return [
            $headers,
            $template_data
        ];
    }
}
