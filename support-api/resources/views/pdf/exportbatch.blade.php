<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>

@include('components.style')

<body>

    <div>
        <h1>Indice</h1>
        <hr>

        <ul>
        @foreach($tickets as $ticket) 
            <li><a href="#ticket-{{ $ticket['data']['id']}}">Ticket #{{ $ticket['data']['id']}}</a></li>
        @endforeach
        </ul>
    </div>


    <div class="page-break"></div>

    @foreach($tickets as $ticket) 
    <div id="ticket-{{ $ticket['data']['id']}}">
        <h1 class="main-header">Report Esteso Ticket #{{ $ticket['data']['id']}}</h1>
        <hr>
        <div class="box">
            <p class="box-heading"><b>Descrizione<b></p>
            <p>
                {{ $ticket['data']['description']}}
            </p>
        </div>
            
        <table style="width:100%">
            <tbody>
                <tr>
                    <td style="vertical-align: top; width:50%;" class="box">
                        <p class="box-heading"><b>Dati webform<b></p>
                        @foreach ($ticket['webform_data'] as $key => $value)
                            @switch($key)
                                @case('description')
                                @break

                                @case('referer')
                                    <p><b>Referente</b><br> {{ $value }}</p>
                                @break

                                @case('referer_it')
                                    <p><b>Referente IT</b><br> {{ $value }}</p>
                                @break

                                @case('office')
                                    <p><b>Sede</b><br> {{ $value }}</p>
                                @break

                                @default
                                    <p><b>{{ $key }}</b><br> {{ $value }}</p>
                            @endswitch
                        @endforeach
                    </td>
                    <td style="vertical-align: top; width:50%;" class="box">
                        <p class="box-heading"><b>Avanzamento<b></p>
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

        <div class="box">
            <p class="box-heading"><b>Messaggio di chiusura</b></p>
            <p>
                {{ $ticket['closing_message'] }}
            </p>
        </div>

        <h2>Note interne</h2>
        <hr>
    
        <div class="box">
            @foreach ($ticket['data']['statusUpdates'] as $key => $value)
                <div>
                    <p><b>Supporto</b></p>
                    <p>
                        {{ $value->content }}
                    </p>
                </div>
            @endforeach
        </div>
    
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