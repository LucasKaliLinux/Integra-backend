<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Ações</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');
        
        @page {
            margin: 30mm 10mm 10mm 10mm;
            size: landscape;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
            color: #333;
            margin-top: 60px;
        }

        .container {
            padding: 2%;
            margin: 0 auto;
            max-width: 100%;
        }

        .header {
            position: fixed;
            top: 0;
            left: 2%;
            right: 0;
            display: table;
            width: 96%;
            margin-bottom: 15px;
            border-bottom: 2px solid #1e40af;
            padding-bottom: 10px;
        }

        .header-logo {
            display: table-cell;
            width: 140px;
            vertical-align: middle;
        }

        .header-logo img {
            width: 120px;
            height: auto;
        }

        .header-title {
            font-family: "Montserrat", sans-serif;
            font-weight: 700;
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            padding-right: 2%;
        }

        .header-title h1 {
            font-size: 16pt;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .header-title p {
            font-size: 8pt;
            color: #666;
        }

        .filters {
            background: #f3f4f6;
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 4px;
            font-size: 7pt;
        }

        .filters strong {
            color: #1e40af;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        thead {
            background: #1e40af;
            color: white;
            display: table-header-group;
        }

        tbody tr {
            page-break-inside: avoid;
        }

        tbody {
            page-break-before: auto;
        }

        th {
            padding: 6px 4px;
            text-align: left;
            font-size: 7pt;
            font-weight: 600;
            border: 1px solid #1e40af;
        }

        td {
            padding: 5px 4px;
            border: 1px solid #ddd;
            font-size: 7pt;
        }

        /* ⬇️ NOVO: Linha da ação (par/ímpar) */
        tbody tr.acao-row:nth-child(4n+1) {
            background: #ffffff;
        }

        tbody tr.acao-row:nth-child(4n+3) {
            background: #f9fafb;
        }

        /* ⬇️ NOVO: Linha de observação (logo abaixo da ação) */
        tr.observacao-row {
            background: #fef3c7 !important; /* Fundo amarelo claro */
        }

        tr.observacao-row td {
            padding: 8px 12px;
            border-left: 3px solid #f59e0b; /* Borda laranja */
            font-style: italic;
            color: #92400e;
            font-size: 7pt;
        }

        .text-right {
            text-align: right;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 6pt;
            font-weight: 600;
        }

        .badge-federal {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-estadual {
            background: #f3e8ff;
            color: #7c3aed;
        }

        .badge-solicitado {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-em_articulacao {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-aprovada {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-em_execucao {
            background: #e9d5ff;
            color: #6b21a8;
        }

        .badge-concluida {
            background: #d1fae5;
            color: #065f46;
        }

        .footer {
            position: fixed;
            bottom: 5mm;
            left: 10mm;
            right: 10mm;
            text-align: center;
            font-size: 7pt;
            color: #666;
            padding: 8px 0;
            border-top: 1px solid #ddd;
        }

        .truncate {
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ⬇️ NOVO: Label da observação */
        .obs-label {
            font-weight: 600;
            color: #92400e;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header --}}
        <div class="header">
            <div class="header-logo">
                <img src="{{ public_path('assets/alba-logo.png') }}" alt="ALBA">
            </div>
            <div class="header-title">
                <h1>RELATÓRIO DE AÇÕES</h1>
                <p>Assembleia Legislativa da Bahia</p>
            </div>
        </div>

        {{-- Filtros aplicados --}}
        @if(!empty($filtros))
        <div class="filters">
            <strong>Total:</strong> {{ $total }} ações
        </div>
        @endif

        {{-- Tabela --}}
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">Gov.</th>
                    <th style="width: 24%;">Título</th>
                    <th style="width: 5%;">Órgão</th>
                    <th style="width: 10%;">Categoria</th>
                    <th style="width: 10%;">Município</th>
                    <th style="width: 8%;" class="text-right">Valor (R$)</th>
                    <th style="width: 4%;">Ano</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 6%;">Tipo</th>
                    <th style="width: 8%;">Liderança</th>
                    <th style="width: 7%;">N° SEI</th>
                </tr>
            </thead>
            <tbody>
                @foreach($acoes as $acao)
                    {{-- ⬇️ Linha principal da ação --}}
                    <tr class="acao-row">
                        <td>
                            <span class="badge badge-{{ strtolower($acao['governo']) }}">
                                {{ $acao['governo'] }}
                            </span>
                        </td>
                        <td title="{{ $acao['titulo'] }}">{{ $acao['titulo'] }}</td>
                        <td>{{ $acao['orgao'] }}</td>
                        <td>{{ $acao['categoria'] }}</td>
                        <td>{{ $acao['municipio'] }}</td>
                        <td class="text-right">{{ number_format($acao['valor'], 2, ',', '.') }}</td>
                        <td>{{ $acao['ano'] }}</td>
                        <td>
                            <span class="badge badge-{{ $acao['status_slug'] }}">
                                {{ $acao['status'] }}
                            </span>
                        </td>
                        <td>{{ $acao['tipo'] }}</td>
                        <td>{{ $acao['lideranca'] }}</td>
                        <td>{{ $acao['numero_sei'] ?: '-' }}</td>
                    </tr>

                    {{-- ⬇️ NOVO: Linha de observação (só aparece se tiver) --}}
                    @if(!empty($acao['observacao']))
                    <tr class="observacao-row">
                        <td colspan="11">
                            <span class="obs-label">Observação:</span> {{ $acao['observacao'] }}
                        </td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <strong>everestmkt.com.br/integra</strong> | 
        Gerado em {{ $dataGeracao }} por {{ $usuario }}
    </div>
</body>
</html>