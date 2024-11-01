<?php
/**
 * Add new receiver form.
 *
 * @package virtuaria/pagseguro/split
 */

defined( 'ABSPATH' ) || exit;

if ( isset( $_POST['_wpnonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'add-seller' ) ) {
	$seller_name  = isset( $_POST['seller_name'] ) ? sanitize_text_field( wp_unslash( $_POST['seller_name'] ) ) : '';
	$seller_email = isset( $_POST['seller_email'] ) ? sanitize_text_field( wp_unslash( $_POST['seller_email'] ) ) : '';
}
?>

<form action="" method="post" id="new-seller">
	<h2 class="title">Adicionar Novo Seller</h2>
	<p class="description">
		Um link para definição da senha de acesso ao painel será enviado por email.
	</p>
	<label for="seller-name">Login</label>
	<input
		required
		type="text"
		name="seller_name"
		id="seller-name"
		pattern="[A-Za-z0-9]+"
		placeholder="Nome de usuário que será usado para login no painel"
		value="<?php echo esc_attr( $seller_name ); ?>"
		onkeyup="this.value = this.value.replace(/[^a-zA-Z0-9]+/g, '')" />

	<small>Não é permitido espaços ou caracteres especiais.</small>
	<label for="seller-email">Email</label>
	<input
		type="text"
		name="seller_email"
		id="seller-email"
		required
		placeholder="Digite o email do seller"
		pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
		value="<?php echo esc_attr( $seller_email ); ?>"/>
	<label for="seller-fee">Taxa (%)</label>
	<input
		type="number"
		name="seller_fee"
		id="seller-fee"
		min="0"
		max="100"
		step="0.01"
		placeholder="Percentual sobre as vendas" />
	<button class="button button-primary" type="submit">Adicionar</button>
	<?php wp_nonce_field( 'add-seller' ); ?>
</form>
