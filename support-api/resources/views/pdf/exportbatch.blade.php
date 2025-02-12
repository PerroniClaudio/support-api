<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>

@include('components.style')

<body>

    <div style="text-align:center; height:100%">

        <img src="{{ base_path().'/storage/app/public/iftlogo.png' }}" alt="iftlogo" width="192" height="38">

        <h1 class="main-header" style="font-size:3rem;line-height: 1;margin-top: 4rem;margin-bottom: 4rem;">{{ $company['name'] }}</h1>

        <h3>Periodo</h3>
        <p>{{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>

    </div>


    <div class="page-break"></div>

    <div>

        <div style="text-align:center;margin-top: 1rem;margin-bottom: 1rem;">
            <p>Periodo: {{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>
        </div>
        

        @php
            $incident = 0;
            $request = 0;
            $total = 0;
        @endphp

        @foreach($tickets as $ticket) 

            @if($ticket['data']['ticketType']['category']['is_problem'] == "1")
                @php
                    $incident++;
                @endphp
            @else
                @php
                    $request++;
                @endphp
            @endif

            @php 
                $total++;
            @endphp

        @endforeach

        <table width="100%">
            <tr>
                <td style="width:30%;text-align:center;">
                    <div class="box">
                        <h3>Incident</h3>
                        <p>{{ $incident }}</p>
                    </div>
                </td>
                <td style="width:30%;text-align:center;">
                    <div class="box">
                        <h3>Request</h3>
                        <p>{{ $request }}</p>
                    </div>
                </td>
                <td style="width:30%;text-align:center;">
                    <div class="box">
                        <h3>Totale</h3>
                        <p>{{ $total }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <div>
            <h3>Ticket aperti per giornata</h3>

            <table style="width:100%">
                <thead>
                    <th><b>Data</b></th>
                    <th style="text-align:center"><b>Request</b></th>
                    <th style="text-align:center"><b>Incident</b></th>
                </thead>

                <tbody>
                    @for ($date = $date_from; $date <= $date_to; $date->addDay())
                        <tr>
                            <td>{{ $date->format('d/m/Y') }}</td>
                            <th style="text-align:right">{{ isset($ticket_graph_data[$date->format('Y-m-d')]) ? $ticket_graph_data[$date->format('Y-m-d')]['requests'] : '0' }}</td>
                            <th style="text-align:right">{{ isset($ticket_graph_data[$date->format('Y-m-d')]) ? $ticket_graph_data[$date->format('Y-m-d')]['incidents'] : '0' }}</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>


    </div>

    <div class="page-break"></div>

    <div>
        <h1>Indice</h1>
        <hr>

        <table style="width:100%">
            <tbody>
                @foreach($tickets as $ticket) 
                    <tr>
                        <td style="width:25%"><a href="#ticket-{{ $ticket['data']['id']}}">Ticket #{{ $ticket['data']['id']}}</a></td>
                        <td style="width:25%">{{ $ticket['data']['created_at']->format('d/m/Y H:i') }}</td>
                        <td style="width:25%">@if($ticket['data']['ticketType']['category']['is_problem'] == "1")Incident @else Request @endif</td>
                        <td style="width:25%">
                            @if($ticket['data']['user']['is_admin'] == "0")
                                {{ $ticket['data']['user']['name'] }} {{ $ticket['data']['user']['surname'] }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>


    <div class="page-break"></div>

        
    @foreach($tickets as $ticket) 
    <div id="ticket-{{ $ticket['data']['id']}}">
        {{-- Questa non è la versione estesa <h1 class="main-header">Report Esteso Ticket #{{ $ticket['data']['id']}}</h1> --}}
        <h1 class="main-header">Report Esteso Ticket #{{ $ticket['data']['id']}}</h1>
        <hr>
        <div class="box">
            <p class="box-heading"><b>Descrizione</b></p>
            <p>
                {{ $ticket['data']['description']}}
            </p>
        </div>
            
        <table style="width:100%">
            <tbody>
                <tr>
                    <td style="vertical-align: top; width:50%;" class="box">
                        <p class="box-heading"><b>Dati webform</b></p>
                        @foreach ($ticket['webform_data'] as $key => $value)
                            @switch($key)
                                @case('description')
                                @break

                                @case('referer')
                                    <p><b>Utente interessato</b><br> {{ $value }}</p>
                                @break

                                @case('referer_it')
                                    <p><b>Referente IT</b><br> {{ $value }}</p>
                                @break

                                @case('office')
                                    <p><b>Sede</b><br> {{ $value }}</p>
                                @break

                                @default
                                    @if(is_array($value))
                                        <p><b>{{ $key }}</b><br> {{ implode(', ', $value) }}</p>
                                    @else
                                        <p><b>{{ $key }}</b><br> {{ $value }}</p>
                                    @endif
                            @endswitch
                        @endforeach
                    </td>
                    <td style="vertical-align: top; width:50%;" class="box">
                        <p class="box-heading"><b>Avanzamento</b></p>
                        <table>
                            <thead>
                                <tr>
                                    <th style="text-align: left">Stato</th>
                                    <th style="text-align: left">Numero</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>In Attesa</td>
                                    <td>{{ $ticket['status_updates']['attesa'] }}</td>
                                </tr>
                                <tr>
                                    <td>Assegnato</td>
                                    <td>{{ $ticket['status_updates']['assegnato'] }}</td>
                                </tr>
                                <tr>
                                    <td>In Corso</td>
                                    <td>{{ $ticket['status_updates']['in_corso'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="box" style="margin-top:.5rem;">
            <p class="box-heading"><b>Messaggio di chiusura</b></p>
            <p>
                {{ $ticket['closing_message'] }}
            </p>
        </div>

        {{-- La parte commentata non è per il cliente e questo file per ora viene utilizzato per creare report da portare poi al cliente --}}
        {{-- <table style="width:100%">
            <tbody>
                <tr>
                    <td style="vertical-align: top; width:50%;" class="box">
                        <p class="box-heading"><b>Responsabilità</b></p>
                        <p>
                            {{ $ticket['data']['is_user_error'] ? 'Cliente' : 'Supporto' }}
                        </p>
                    </td>
                    <td style="vertical-align: top; width:50%;" class="box">
                        <p class="box-heading"><b>Tempo di elaborazione previsto<b></p>
                        <p>{{ $ticket['data']['ticketType']['expected_processing_time'] == null 
                            ? 'Non impostato'
                            : (
                                $ticket['data']['ticketType']['expected_processing_time'] / 60 . ' ore' . 
                                ($ticket['data']['ticketType']['expected_processing_time'] % 60 != 0 
                                    ? ' e ' . $ticket['data']['ticketType']['expected_processing_time'] % 60 . ' minuti' 
                                    : '' )
                            ) 
                        }}</p>
                        <p class="box-heading"><b>Tempo di elaborazione effettivo</b></p>
                        <p>{{ $ticket['data']['actual_processing_time'] 
                            ? $ticket['data']['actual_processing_time'] / 60 . ' ore' . 
                                ($ticket['data']['actual_processing_time'] % 60 != 0 
                                    ? ' e ' . $ticket['data']['actual_processing_time'] % 60 . ' minuti' 
                                    : '' )
                            : 'Non impostato'    
                        }}</p>
                    </td>
                </tr>
            </tbody>
        </table> --}}

       
        <h2>Messaggi</h2>
        <hr>
        <div class="box">
            @foreach ($ticket['data']['messages'] as $key => $value)
                @if ($loop->first)
                    @continue
                @endif
           
                    <table style="width:100%">
                        <tr>
                            <td style="vertical-align: top; width:70%;">
                                @if ($value->user->is_admin == 1)
                                    <p><b>Supporto - Update</b></p>
                                @else
                                    <p><b>{{ $value->user->name }} {{ $value->user->surname }}</b></p>
                                @endif
                            </td>
                            <td style="vertical-align: top; width:30%;">
                                <p style="text-align: right">{{ $value->created_at->format('d/m/Y H:i') }}</p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"> <p>{{ $value->message }}</p></td>
                        </tr>
                    </table>
    
            @endforeach
        </div>
    </div>

    <div class="page-break"></div>
    @endforeach




</body>