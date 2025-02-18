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

        <img src="data:image/png;base64,{{ base64_encode(file_get_contents("https://frontend.ifortech.com/images/ifortech.png")) }}"
            alt="iftlogo" 
            style="width: 192px; height: 38px;"
        >


        <h1 class="main-header" style="font-size:3rem;line-height: 1;margin-top: 4rem;margin-bottom: 4rem;">
            {{ $company['name'] }}</h1>

        {{-- @php
            $logoUrl = $company->temporaryLogoUrl();
        @endphp
        @if ($logoUrl)
            <img src="data:image;base64, {{ base64_encode(file_get_contents($logoUrl)) }}" alt="Company Logo"
                style="max-height: 100px; max-width: 200px;">
        @endif --}}

        <h3>Periodo</h3>
        <p>{{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>

    </div>


    <div class="page-break"></div>

    <div>

        <div style="text-align:center;margin-top: 1rem;margin-bottom: 1rem;">
            <p>Periodo: {{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>
        </div>

        <div class="card">
            <table width="100%">
                <tr>
                    <td>

                        <b>Ticket per servizio</b>
                    </td>
                    <td>
                        <table width="100%">
                            <tr>
                                <td>
                                    <p style="font-weight: 600">{{ count($tickets) }}</p>
                                    <p class="text-small">Ticket creati nel periodo</p>
                                </td>
                                <td>
                                    <p style="font-weight: 600">{{ $closed_tickets_count }}</p>
                                    <p class="text-small">Ticket risolti nel periodo</p>
                                </td>
                                <td>
                                    <p style="font-weight: 600">{{ $other_tickets_count }}</p>
                                    <p class="text-small">Ticket in Lavorazione</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table width="100%">
                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_category_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_source_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_weekday_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>

                    @if ($dates_are_more_than_one_month_apart)
                        <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                            <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_per_month_url)) }}"
                                style="width: 100%; height: auto;">
                        </td>
                    @endif
                </tr>
            </table>
        </div>

        <table width="100%" style="margin-top: 1rem;">
            <tr>
                <td>
                    <table width="100%">
                        <tr>
                            <td>
                                <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_priority_url)) }}"
                                    style="width: 100%; height: auto;">
                            <td>
                        </tr>
                        <tr>
                            <td>
                                <img src="data:image/png;base64,{{ base64_encode(file_get_contents($tickets_by_user_url)) }}"
                                    style="width: 100%; height: auto;">
                            <td>
                        </tr>
                    </table>
                </td>

                <td>
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents($tickets_sla_url)) }}"
                        style=" width:100%;height: auto;">
                </td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    <div>
        <div style="text-align:center;margin-top: 1rem;margin-bottom: 1rem;">
            <p>Periodo: {{ $date_from->format('d/m/Y') }} - {{ $date_to->format('d/m/Y') }}</p>
        </div>

        <div class="card">
            <table width="100%">
                <tr>
                    <td>

                        <b>Ticket per tipologia</b>
                    </td>
                    <td>
                        <table width="100%">
                            <tr>
                                <td>
                                    <p style="font-weight: 600">{{ count($tickets) }}</p>
                                    <p class="text-small">Ticket creati nel periodo</p>
                                </td>
                                <td>
                                    <p style="font-weight: 600">{{ $request_number }}</p>
                                    <p class="text-small">Numero di Incident</p>
                                </td>
                                <td>
                                    <p style="font-weight: 600">{{ $incident_number }}</p>
                                    <p class="text-small">Numero di Request</p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table width="100%">
                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_category_incident_bar_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_category_request_bar_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_type_incident_bar_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>


                    <td style="background-color: #fff;border-radius: 8px; padding: 4px">
                        <img src="data:image/png;base64,{{ base64_encode(file_get_contents($ticket_by_type_request_bar_url)) }}"
                            style="width: 100%; height: auto;">
                    </td>

                </tr>
            </table>
        </div>

    </div>

    <div class="page-break"></div>

    <div>
        <h1>Indice</h1>
        <hr>

        <table style="width:100%">
            <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td style="width:25%"><a href="#ticket-{{ $ticket['id'] }}">Ticket
                                #{{ $ticket['id'] }}</a></td>
                        <td style="width:25%">{{ $ticket['opened_at'] }}</td>
                        <td style="width:25%">
                            {{ $ticket['incident_request'] }}
                        </td>
                        <td style="width:25%">
                            {{ $ticket['opened_by'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>


    </div>


    <div class="page-break"></div>


    @foreach ($tickets as $ticket)
        @if (!is_null($ticket['webform_data']))
            @if (!$ticket['should_show_more'])
                <div id="ticket-{{ $ticket['id'] }}"
                    style="font-size: 0.75rem; !important; line-height: 1rem !important;">
                    <table style="width:100%">
                        <tr>
                            <td style="vertical-align: middle;">
                                <h1 class="main-header">Ticket #{{ $ticket['id'] }}</h1>
                            </td>
                            <td style="vertical-align: middle;">
                                <div
                                    style="text-align:right; background-color: {{ $ticket['incident_request'] == 'Request' ? '#d4dce3' : '#f8d7da' }}; border-radius: 8px; padding: 8px;font-weight: bold;">
                                    {{ $ticket['incident_request'] }}
                                </div>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <table class="width:100%">
                        <td style="width: 28%">
                            <div style="margin-bottom:.5rem" class="card">
                                <div>
                                    <b>Data di apertura</b> <br>
                                    <span>{{ $ticket['opened_at'] }}</span>
                                </div>

                                <div>
                                    <b>Aperto da</b> <br>
                                    <span>{{ $ticket['opened_by'] }}</span>
                                </div>

                                <div>
                                    <b>Categoria</b> <br>
                                    <span>{{ $ticket['category'] }}</span>
                                </div>
                                <div>
                                    <b>Tipologia</b> <br>
                                    <span>{{ $ticket['type'] }}</span>
                                </div>
                            </div>
                            <div style="margin-bottom:.5rem" class="box">
                                <p class="box-heading"><b>Dati webform</b></p>
                                @if (!is_null($ticket['webform_data']))
                                    @foreach ($ticket['webform_data'] as $key => $value)
                                        @switch($key)
                                            @case('description')
                                            @break

                                            @case('referer')
                                                <span><b>Utente interessato</b><br> {{ $value }}</span> <br>
                                            @break

                                            @case('referer_it')
                                                <span><b>Referente IT</b><br> {{ $value }}</span> <br>
                                            @break

                                            @case('office')
                                                <span><b>Sede</b><br> {{ $value }}</span> <br>
                                            @break

                                            @default
                                                @if (is_array($value))
                                                    <span><b>{{ $key }}</b><br> {{ implode(', ', $value) }}</span>
                                                    <br>
                                                @else
                                                    <span><b>{{ $key }}</b><br> {{ $value }}</span> <br>
                                                @endif
                                        @endswitch
                                    @endforeach
                                @endif
                            </div>
                            <div style="margin-bottom:.5rem" class="box">
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
                            </div>
                        </td>
                        <td style="width: 68%">
                            <div class="box" style="margin-bottom:.5rem">
                                <p class="box-heading"><b>Descrizione</b></p>
                                <span>
                                    {{ $ticket['description'] }}
                                </span>
                            </div>

                            <div class="box" style="margin-bottom:.5rem">
                                <p class="box-heading"><b>Messaggi</b></p>


                                @foreach ($ticket['messages'] as $key => $value)
                                    @if ($loop->first)
                                        @continue
                                    @endif

                                    <table style="width:100%">
                                        <tr>
                                            <td style="vertical-align: top; width:70%;font-weight: bold">

                                                {{ $value['user'] }}
                                            </td>
                                            <td style="vertical-align: top; width:30%;">
                                                <span style="text-align: right">{{ $value['created_at'] }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">
                                                <span>{{ $value['message'] }}</span>
                                            </td>
                                        </tr>
                                    </table>
                                @endforeach

                            </div>

                            <div class="box">
                                <p class="box-heading"><b>Messaggio di chiusura</b></p>
                                <span>
                                    {{ $ticket['closing_message']['message'] }}
                                </span>
                            </div>
                        </td>
                    </table>
                </div>

                <div class="page-break"></div>
            @else
                <div id="ticket-{{ $ticket['id'] }}"
                    style="font-size: 0.75rem; !important; line-height: 1rem !important;">
                    <table style="width:100%">
                        <tr>
                            <td style="vertical-align: middle;">
                                <h1 class="main-header">Ticket #{{ $ticket['id'] }}</h1>
                            </td>
                            <td style="vertical-align: middle;">
                                <div
                                    style="text-align:right; background-color: {{ $ticket['incident_request'] == 'Request' ? '#d4dce3' : '#f8d7da' }}; border-radius: 8px; padding: 8px;font-weight: bold;">
                                    {{ $ticket['incident_request'] }}
                                </div>
                            </td>
                        </tr>
                    </table>

                    <hr>

                    <table class="width:100%">
                        <td style="width: 28%">
                            <div style="margin-bottom:.5rem" class="card">
                                <div>
                                    <b>Data di apertura</b> <br>
                                    <span>{{ $ticket['opened_at'] }}</span>
                                </div>

                                <div>
                                    <b>Aperto da</b> <br>
                                    <span>{{ $ticket['opened_by'] }}</span>
                                </div>

                                <div>
                                    <b>Categoria</b> <br>
                                    <span>{{ $ticket['category'] }}</span>
                                </div>
                                <div>
                                    <b>Tipologia</b> <br>
                                    <span>{{ $ticket['type'] }}</span>
                                </div>
                            </div>
                            <div style="margin-bottom:.5rem" class="box">
                                <p class="box-heading"><b>Dati webform</b></p>
                                @if (!is_null($ticket['webform_data']))
                                    @foreach ($ticket['webform_data'] as $key => $value)
                                        @switch($key)
                                            @case('description')
                                            @break

                                            @case('referer')
                                                <span><b>Utente che ha il problema</b><br> {{ $value }}</span> <br>
                                            @break

                                            @case('referer_it')
                                                <span><b>Referente IT</b><br> {{ $value }}</span> <br>
                                            @break

                                            @case('office')
                                                <span><b>Sede</b><br> {{ $value }}</span> <br>
                                            @break

                                            @default
                                                @if (is_array($value))
                                                    <span><b>{{ $key }}</b><br> {{ implode(', ', $value) }}</span>
                                                    <br>
                                                @else
                                                    <span><b>{{ $key }}</b><br> {{ $value }}</span> <br>
                                                @endif
                                        @endswitch
                                    @endforeach
                                @endif
                            </div>
                            <div style="margin-bottom:.5rem" class="box">
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
                            </div>
                        </td>
                        <td style="width: 68%">
                            <div class="box" style="margin-bottom:.5rem">
                                <p class="box-heading"><b>Descrizione</b></p>
                                <span>
                                    {{ $ticket['description'] }}
                                </span>
                            </div>

                            <div class="box" style="margin-bottom:.5rem">
                                <p class="box-heading"><b>Messaggi</b></p>


                                @foreach ($ticket['messages'] as $key => $value)
                                    <table style="width:100%">
                                        <tr>
                                            <td style="vertical-align: top; width:70%;font-weight: bold">

                                                {{ $value['user'] }}
                                            </td>
                                            <td style="vertical-align: top; width:30%;">
                                                <span style="text-align: right">{{ $value['created_at'] }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="2">
                                                <span>{{ $value['message'] }}</span>
                                            </td>
                                        </tr>
                                    </table>
                                @endforeach

                                <p>
                                    <a href="{{ $ticket['ticket_frontend_url'] }}"
                                        style="color: #e73029; font-size: 0.75rem;" target="_blank">
                                        Vedi di più
                                    </a>
                                </p>

                            </div>

                            <div class="box">
                                <p class="box-heading"><b>Messaggio di chiusura</b></p>
                                <span>
                                    {{ $ticket['closing_message']['message'] }}
                                </span>
                            </div>
                        </td>
                    </table>

                </div>

                <div class="page-break"></div>
            @endif
        @endif
    @endforeach




</body>
