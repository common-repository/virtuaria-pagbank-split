<?php
/**
 * Receiver reports.
 *
 * @package virtuaria/pagseguro/split
 */

defined( 'ABSPATH' ) || exit;
?>

<h1 class="list-title">Sellers</h1>
<p>Lista de vendedores da plataforma. <button class="button print-report button-primary" onclick="window.print()">Imprimir Relatório</button></p>

<?php do_action( 'virtuaria_receiver_report_before_content' ); ?>
<table id="all-transations-table" class="display" width="100%"></table>

<div class="chart-container sellers">
	<h2 class="graphics">Vendas por Seller ( últimos 6 meses )</h2>
	<canvas id="salesPieChart" width="500px" height="500px"></canvas>
</div>

<?php do_action( 'virtuaria_receiver_report_after_content' ); ?>
