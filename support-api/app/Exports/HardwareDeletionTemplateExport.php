<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class HardwareDeletionTemplateExport implements FromArray
{
    public function __construct()
    {
    }
    
    public function array(): array {
        $template_data = [];
        $headers = [
            "ID hardware *",
            "Tipo di eliminazione Soft/Definitiva *",
            "ID responsabile dell'eliminazione (deve essere admin o del supporto). Se non indicato viene impostato l'ID di chi carica il file."
        ];
        
        return [
            $headers,
            $template_data
        ];
    }
}
