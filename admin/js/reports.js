function toogleItens() {
    jQuery('.see-itens').on('click', function(){
        if ( ! jQuery(this).hasClass('active') ) {
            jQuery(this).addClass('active');
            jQuery(this).html('Esconder -')
        } else {
            jQuery(this).removeClass('active');
            jQuery(this).html('Ver +')
        }
    });
}

function filterByDate() {
    var table = jQuery('#all-transations-table').DataTable();

    // Extensão para filtragem por data
    jQuery.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            var status = jQuery('#status').val();

            var min = new Date(jQuery('#min-date').val().replace('-','/'));
            var max = new Date(jQuery('#max-date').val().replace('-','/'));

            min = min.getTime();
            max = max.getTime();

            let line_status = data[data.length - 2];
            if (jQuery('#toplevel_page_virtuaria_pagbank_split').length === 1) {
                let parts = data[6].split(' ')[0].replace(',', '').split('/');
                var date = new Date(parts[2], parts[1] - 1, parts[0]);
            } else {
                let parts = data[5].split(' ')[0].replace(',', '').split('/');
                var date = new Date(parts[2], parts[1] - 1, parts[0]);
            }
            
            date = date.getTime();

            if ( status != '' && ( ! isNaN(min) || ! isNaN(max) ) ) {
                if ( checkDateRange(min, max, date)
                    && ( status === 'all' || line_status === status ) ) {
                    return true;
                }
            } else {
                if ( status != '' ) {
                    if ( status === 'all' || line_status === status ) {
                        return true;
                    }
                } else if ( checkDateRange(min, max, date) ) {
                    return true;
                }
            }

            return false;
        }
    );

    function checkDateRange( min, max, date ) {
        if ((isNaN(min) && isNaN(max))
        || (isNaN(min) && date <= max)
        || (min <= date && isNaN(max))
        || (min <= date && date <= max)) {
            return true;
        }
        return false;
    }

    // Event listener para os filtros de data
    jQuery('#min-date, #max-date, #status').change(function() {
        table.draw();
    });
}

function getLanguage() {
    return {
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
    };
}

// Funções de processamento de dados
function processarDados(agrupamento) {
    var dadosProcessados = {};

    Object.keys(sales).forEach(function(data) {
        var chave = agrupamento === 'mensal' ? data.substring(0, 7) : data;
        if (!dadosProcessados[chave]) {
            dadosProcessados[chave] = { total_sold: 0, received: 0, received_seller: 0 };
        }

        dadosProcessados[chave].total_sold += sales[data].total_sold;
        dadosProcessados[chave].received += sales[data].received;
        dadosProcessados[chave].received_seller += sales[data].received_seller;
    });

    var labelsFormatadas = Object.keys(dadosProcessados).sort();
    var total_sold = labelsFormatadas.map(chave => dadosProcessados[chave].total_sold);
    var received = labelsFormatadas.map(chave => dadosProcessados[chave].received);
    var received_seller = labelsFormatadas.map(chave => dadosProcessados[chave].received_seller);

    return {
        labels: labelsFormatadas.map(agrupamento === 'mensal' ? formatarMesAno : formatarData),
        datasets: {
            total_sold: total_sold,
            received: received,
            received_seller: received_seller
        }
    };
}

function formatarData(dataString) {
    var partes = dataString.split('-');
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function formatarMesAno(mesAnoString) {
    var partes = mesAnoString.split('-');
    return partes[1] + '/' + partes[0];
}

// Função de inicialização do gráfico
function inicializarGrafico(dadosProcessados) {
    var ctx = document.getElementById('salesChart').getContext('2d');

     // Verifica se já existe uma instância do gráfico e a destrói antes de criar uma nova
     if (window.salesChart instanceof Chart) {
        window.salesChart.destroy();
    }

    let textReceiver = 'Recebido';
    if ( jQuery('#toplevel_page_virtuaria_pagbank_split').length === 1 ) {
        textReceiver += ' Gestor';
    }

    let dataset = [
        {
            label: 'Vendas Totais',
            data: dadosProcessados.datasets.total_sold,
            backgroundColor: 'rgba(0, 128, 0, 0.8)',
            borderColor: 'rgba(0, 128, 0, 1)',
            borderWidth: 2,
            fill: false // Define se o gráfico deve ser preenchido abaixo da linha
        },
        {
            label: textReceiver,
            data: dadosProcessados.datasets.received,
            backgroundColor: 'rgba(75, 192, 192, 0.8)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 2,
            fill: false
        }
    ];

    if ( jQuery('#toplevel_page_virtuaria_pagbank_split').length === 1 ) {
        dataset.push( {
            label: 'Recebido Sellers',
            data: dadosProcessados.datasets.received_seller,
            backgroundColor: 'rgba(255, 255, 0, 0.8)',
            borderColor: 'rgba(255, 255, 0, 1)',
            borderWidth: 2,
            fill: false // Define se o gráfico deve ser preenchido abaixo da linha
        });
    }

    window.salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dadosProcessados.labels,
            datasets: dataset
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                },
                x: {
                    offset: true,
                    bounds: 'ticks'
                }
            },
            elements: {
                line: {
                    tension: 0
                }
            },
            responsive: true,
            maintainAspectRatio: false,
        }
    });
}

function verificarChartJsECriarGrafico(dadosProcessados) {
    if (typeof Chart === 'function') {
        inicializarGrafico(dadosProcessados);
    } else {
        console.error('Chart.js ainda não foi carregado.');
        // Caso Chart.js ainda não esteja carregado, espera pelo carregamento completo da página.
        window.addEventListener('load', function() {
            inicializarGrafico(dadosProcessados);
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var dadosProcessados = processarDados(sales, 'diario');
    verificarChartJsECriarGrafico(dadosProcessados);
});

// Listener para a caixa de seleção
document.getElementById('selectVista').addEventListener('change', function() {
    var agrupamentoSelecionado = this.value; // Pega o valor atual da caixa de seleção
    var dadosProcessados = processarDados(agrupamentoSelecionado); // Processa os dados novamente com o novo agrupamento
    verificarChartJsECriarGrafico(dadosProcessados); // Atualiza o gráfico com os novos dados processados
});

jQuery(document).ready(function($) {
    $(document).mouseup(function(e) 
    {
        var container = $(".itens-modal");
    
        // if the target of the click isn't the container nor a descendant of the container
        if ( ! $('.see-itens.active').is(e.target)
            && !container.is(e.target)
            && container.has(e.target).length === 0) 
        {
            container.parent().find('.see-itens.active').removeClass('active');
            container.parent().find('.see-itens').html('Ver +');
        }
    });
});