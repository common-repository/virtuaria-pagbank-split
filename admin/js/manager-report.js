jQuery(document).ready(function($) {
    let table = $('#all-transations-table').DataTable({
        data: data,
        columns: [
            {
                'title': 'Pedido',
                'data': 'order_id'
            },
            {
                'title': 'Seller',
                'data': 'seller_name'
            },
            { 
                'title': 'Vendido',
                'data': 'total_sold',
            },
            {
                'title': 'Comissão',
                'data': 'fee',
            },
            {
                'title': 'Recebido',
                'data': 'received'
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

    var grupos = {};
    var contadorGrupo = 1;

    // Percorre cada linha da tabela
    $('#all-transations-table tbody tr').each(function() {
      var numeroPedido = $(this).find('td:first-child').text();

      // Verifica se o número do pedido já existe no objeto de grupos
      if (!grupos[numeroPedido]) {
        grupos[numeroPedido] = contadorGrupo;
        if ( contadorGrupo > 3 ) {
            contadorGrupo = 0;    
        }
        contadorGrupo++;
      }

      // Adiciona a classe de grupo à linha com base no número do pedido
      $(this).addClass('group-' + grupos[numeroPedido]);
    });
});