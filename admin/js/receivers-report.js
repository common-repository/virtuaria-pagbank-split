jQuery(document).ready(function($) {
    let table = $('#all-transations-table').DataTable({
        data: data,
        columns: [
            {
                'title': 'Seller',
                'data': 'name'
            },
            {
                'title': 'E-mail',
                'data': 'mail',
            },
            { 
                'title': 'Status',
                'data': 'status',
            },
            {
                'title': 'Taxa Personalizada',
                'data': 'fee',
            },
            {
                'title': 'Conexão Ativa',
                'data': 'account'
            },
            {
                'title': 'Criado em',
                'data': 'date'
            },
        ],
        "order": [[ 0, 'desc' ]],
        "pageLength": 50,
        "language": {
            "sProcessing":    "Processando...",
            "sLengthMenu":    "Mostrar _MENU_ registros",
            "sZeroRecords":   "Nenhum resultado encontrado",
            "sEmptyTable":    "Nenhum dado disponível nesta tabela",
            "sInfo":          "Mostrando registros de _START_ a _END_ de um total de _TOTAL_ registros",
            "sInfoEmpty":     "Mostrando registros de 0 a 0 de um total de 0 registros",
            "sInfoFiltered":  "(filtrado de um total de _MAX_ registros)",
            "sInfoPostFix":   "",
            "sSearch":        "Buscar:",
            "sUrl":           "",
            "sInfoThousands":  ",",
            "sLoadingRecords": "Carregando...",
            "oPaginate": {
                "sFirst":    "Primeiro",
                "sLast":    "Último",
                "sNext":    "Seguinte",
                "sPrevious": "Anterior"
            },
            "oAria": {
                "sSortAscending":  ": Ative para classificar a coluna em ordem crescente",
                "sSortDescending": ": Ative para classificar a coluna em ordem decrescente"
            }
        }
    });
});

function inicializarGraficoPizza() {
    var ctx = document.getElementById('salesPieChart').getContext('2d');
    var dados = sales;
    var labels = Object.keys(dados);
    var valores = labels.map(label => dados[label]);

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: valores,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 206, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    // Adicione mais cores conforme necessário
                ],
                borderColor: [
                    'rgba(255,99,132,1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    // Adicione mais bordas conforme necessário
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            var label = context.label || '';
                            var valor = context.raw; // 'raw' contém o valor original não processado
                            return label + ' | ' + formatarValorMonetario( valor );
                        }
                    }
                }
            }
        },
    });
}

function verificarChartJsECriarGrafico() {
    if (typeof Chart === 'function') {
        inicializarGraficoPizza();
    } else {
        console.error('Chart.js ainda não foi carregado.');
        // Caso Chart.js ainda não esteja carregado, espera pelo carregamento completo da página.
        window.addEventListener('load', function() {
            inicializarGraficoPizza();
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    verificarChartJsECriarGrafico();
});

function formatarValorMonetario(valor) {
    valor *= 1.00;
    return 'R$ ' + valor.toFixed(2).replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
}