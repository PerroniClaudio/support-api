# Implementation Plan

- [x] 1. Configurazione iniziale e feature flag

  - Creare la configurazione per i tenant abilitati
  - Implementare la classe DocumentFeatures per gestire l'attivazione della funzionalità
  - _Requirements: 4.1, 4.2, 4.3_

- [x] 1.1 Creare la configurazione dei tenant nel file features-tenants.php

  - Aggiungere la sezione 'documents' con i tenant abilitati (Domustart)
  - Escludere esplicitamente i tenant non abilitati (Spreetziit)
  - _Requirements: 4.1, 4.2_

- [x] 1.2 Implementare la classe DocumentFeatures

  - Creare il file DocumentFeatures.php nel namespace App\Features
  - Implementare i metodi per verificare se un tenant è abilitato
  - Definire i metodi per le singole funzionalità (list, upload, download, delete, search)
  - _Requirements: 4.1, 4.2, 4.3_

- [x] 2. Creazione del modello Document

  - Creare il modello Document con i campi necessari
  - Implementare le relazioni con Company e User
  - Configurare il trait Searchable per la ricerca
  - _Requirements: 1.1, 1.3, 3.1, 3.2_

- [x] 2.1 Creare la migrazione per la tabella documents

  - Definire tutti i campi necessari (name, uploaded_name, type, mime_type, path, company_id, uploaded_by, file_size)
  - Aggiungere le chiavi esterne per company_id e uploaded_by
  - Aggiungere gli indici appropriati per migliorare le performance
  - _Requirements: 1.1, 3.1, 3.2_

- [x] 2.2 Implementare il modello Document

  - Creare la classe Document che estende Model
  - Aggiungere i trait HasFactory e Searchable
  - Definire i campi fillable
  - Implementare il metodo toSearchableArray per Laravel Scout
  - Implementare le relazioni con Company e User
  - _Requirements: 1.1, 1.3, 3.1, 5.1_

- [x] 3. Implementazione del DocumentController

  - Creare il controller con i metodi necessari
  - Implementare i controlli di accesso basati sui ruoli
  - Gestire la visibilità a livello di condominio
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3_

- [x] 3.1 Implementare il metodo index per elencare i documenti

  - Verificare i permessi dell'utente (is_admin o is_company_admin)
  - Filtrare i documenti in base al condominio dell'utente
  - Implementare i filtri per tipo di file e data
  - Restituire i documenti con i metadati appropriati
  - _Requirements: 1.3, 2.1, 2.2, 2.3, 3.1_

- [x] 3.2 Implementare il metodo store per caricare documenti e creare cartelle

  - Verificare che l'utente sia un amministratore (is_admin = 1)
  - Validare i dati di input
  - Gestire separatamente la creazione di cartelle e il caricamento di file
  - Salvare i file su Google Cloud Storage
  - Creare il record nel database
  - _Requirements: 1.1, 1.2, 2.1, 3.2_

- [x] 3.3 Implementare il metodo downloadFile per scaricare documenti

  - Verificare che l'utente abbia accesso al documento
  - Generare un URL temporaneo per il download
  - _Requirements: 2.1, 2.2, 3.1, 5.1_

- [x] 3.4 Implementare il metodo destroy per eliminare documenti

  - Verificare che l'utente sia un amministratore (is_admin = 1)
  - Eliminare il documento dal database
  - _Requirements: 1.4, 2.1_

- [x] 3.5 Implementare i metodi search e searchByCompany per cercare documenti

  - Verificare i permessi dell'utente
  - Implementare la ricerca utilizzando Laravel Scout
  - Filtrare i risultati in base al condominio dell'utente
  - _Requirements: 1.5, 2.1, 2.2, 3.1, 3.3_

- [x] 4. Configurazione delle rotte API

  - Definire le rotte per le operazioni CRUD sui documenti
  - Applicare i middleware di autenticazione e autorizzazione
  - _Requirements: 2.1, 2.2, 2.3_

- [x] 4.1 Aggiungere le rotte nel file routes/api.php

  - Definire le rotte per index, store, downloadFile, destroy, search
  - Raggruppare le rotte sotto un middleware di autenticazione
  - Applicare il middleware per il controllo delle feature flag
  - _Requirements: 2.1, 2.2, 2.3, 4.1, 4.2_
