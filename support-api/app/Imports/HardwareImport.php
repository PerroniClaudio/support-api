<?php
namespace App\Imports;

use App\Models\Company;
use App\Models\Hardware;
use App\Models\HardwareAuditLog;
use App\Models\HardwareType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class HardwareImport implements ToCollection
{
    // TEMPLATE IMPORT:
    // "Marca",
    // "Modello",
    // "Seriale",
    // "Tipo (testo, preso dalla lista nel gestionale)",
    // "Data d'acquisto (gg/mm/aaaa)",
    // "Proprietà",
    // "Specificare (se proprietà è Altro)",
    // "Cespite aziendale",
    // "Note",
    // "ID Azienda"
    // "ID utenti (separati da virgola)"

    protected $authUser;

    public function __construct($authUser)
    {
        $this->authUser = $authUser;
    }
    
    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                // Deve saltare la prima riga contentente i titoli
                if (strpos(strtolower($row[2]), 'seriale') !== false) {
                    continue;
                }

                if (empty($row[2])) {
                    throw new \Exception('Il campo seriale è vuoto in una delle righe.');
                }

                $isPresent = Hardware::where('serial_number', $row[2])->first();

                if ($isPresent) {
                    throw new \Exception('Hardware con seriale ' . $row[2] . ' già presente');
                    // continue;
                }

                
                $hardwareType = HardwareType::whereRaw('LOWER(name) = ?', [strtolower($row[3])])->first();
                if(!$hardwareType) {
                    throw new \Exception('Tipo hardware non trovato per l\'hardware con seriale ' . $row[2]);
                }
                
                if($row[9] != null){
                    $isCompanyPresent = Company::find($row[9]);
                    if(!$isCompanyPresent) {
                        throw new \Exception('ID Azienda errato per l\'hardware con seriale ' . $row[2]);
                    }
                }

                // 'hardware_ownership_types' => [
                //     "owned" => "Proprietà",
                //     "rented" => "Noleggio",
                //     "other" => "Altro",
                // ],
                $hardwareOwnershipTypes = config('app.hardware_ownership_types');
                $lowerOwnershipTypes = array_map('strtolower', $hardwareOwnershipTypes);
                $ownershipType = array_search(strtolower($row[5]), $lowerOwnershipTypes);
                if(!(in_array(strtolower($row[5]), $lowerOwnershipTypes))
                ) {
                    throw new \Exception('1 - Tipo di proprietà non valido per l\'hardware con seriale ' . $row[2] . 'valore: ' . $row[5] . ' - Possibili valori: ' . implode(', ', $lowerOwnershipTypes));
                }
                if(!$ownershipType
                ) {
                    throw new \Exception('2 - Tipo di proprietà non valido per l\'hardware con seriale ' . $row[2]);
                }

                // Gestione della data di acquisto
                $purchaseDate = null;
                if (!empty($row[4])) {
                    try {
                        if (is_numeric($row[4])) {
                            // Converti il numero seriale di Excel in una data
                            $purchaseDate = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[4]));
                        } else {
                            $purchaseDate = Carbon::createFromFormat('d/m/Y', $row[4]);
                        }
                    } catch (\Exception $e) {
                        throw new \Exception('Formato data non valido per l\'hardware con seriale ' . $row[2] . '. Valore: ' . $row[4]);
                    }
                }

                $hardware = Hardware::create([
                    'make' => $row[0],
                    'model' => $row[1],
                    'serial_number' => $row[2],
                    'hardware_type_id' => $hardwareType->id ?? null,
                    'purchase_date' => $purchaseDate,
                    'ownership_type' => $ownershipType ?? null,
                    'ownership_type_note' => $row[6] ?? null,
                    'company_asset_number' => $row[7] ?? null,
                    'notes' => $row[8] ?? null,
                    'company_id' => $row[9] ?? null,
                ]);

                if(isset($hardware->company_id)){
                    HardwareAuditLog::create([
                        'modified_by' => $this->authUser->id,
                        'hardware_id' => $hardware->id,
                        'log_subject' => 'hardware_company',
                        'log_type' => 'created',
                        'new_data' => json_encode(['company_id' => $hardware->company_id]),
                    ]);
                }
                
                if($row[10] != null) {
                    if($row[9] == null) {
                        throw new \Exception('ID Azienda mancante per l\'hardware con seriale ' . $row[2]);
                    }
                    $isCorrect = User::where('company_id', $row[9])->whereIn('id', explode(',', $row[10]))->count() == count(explode(',', $row[10]));
                    if(!$isCorrect) {
                        throw new \Exception('ID utenti errati per l\'hardware con seriale ' . $row[2]);
                    }
                    $users = explode(',', $row[10]);
                    $hardware->users()->sync($users);

                    foreach ($users as $user) {
                        HardwareAuditLog::create([
                            'modified_by' => $this->authUser->id,
                            'hardware_id' => $hardware->id,
                            'log_subject' => 'hardware_user',
                            'log_type' => 'created',
                            'new_data' => json_encode(['user_id' => $user]),
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Errore durante l\'importazione dell\'hardware: ' . $e->getMessage());
            throw $e;
        }
    }
}