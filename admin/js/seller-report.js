jQuery(document).ready(function($) {
    let table = $('#all-transations-table').DataTable({
        data: data,
        columns: [
            {
                'title': 'Pedido',
                'data': 'order_id'
            },
            {
                'title': 'Recebido',
                'data': 'received'
            },
            {
                'title': 'Comissão',
                'data': 'fee',
            },
            { 
                'title': 'Total da Venda',
                'data': 'total_sold',
            },
            {
                'title': 'Método',
                'data': 'payment_method'
            },
            {
                'title': 'Data',
                'data': 'created_at'
            },
            { 
                'title': 'Status',
                'data': 'status'
            },
            {
                'title': 'Detalhes',
                'data': 'itens'
            }
        ],
        "order": [[ 0, 'desc' ]],
        "pageLength": 50,
        "language": getLanguage()
    });

    toogleItens();

    filterByDate();
});
