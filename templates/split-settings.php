<?php
/**
 * Template Split settings.
 *
 * @package Virtuaria/Payments/PagSeguro.
 */

defined( 'ABSPATH' ) || exit;

do_action( 'virtuaria_pagseguro_save_split_settings' );

$options = get_option( 'woocommerce_virt_pagseguro_settings' );

?>
<h1 class="main-title">Virtuaria PagSeguro Split</h1>
<form action="" method="post" id="mainform" class="main-setting">
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_pagseguro_environment">Habilitar </label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Habilitar</span></legend>
						<input type="checkbox" name="woocommerce_virt_pagseguro_split_enabled" id="split" value="yes" <?php echo checked( 'yes', $options['split_enabled'] ); ?>>
						<p class="description">
							Ativa o Split de pagamentos. Após ativar, é obrigatório ir até a aba Integração e fazer uma conexão / reconexão com o PagSeguro para o sistema reconhecer a conta principal.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_pagseguro_main_fee">Taxa Geral (%)</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Taxa Geral</span></legend>
						<input class="input-text regular-input wc_input_price" step="0.01" min="0" type="number" name="woocommerce_virt_pagseguro_main_fee" id="woocommerce_virt_pagseguro_main_fee" value="<?php echo isset( $options['main_fee'] ) ? esc_attr( $options['main_fee'] ) : ''; ?>" >
						<p class="description">
							Define a taxa padrão descontada das vendas dos sellers.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_pagseguro_marketplace">
						Conta Principal
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Conta Principal</span></legend>
						<?php echo isset( $options['marketplace'] ) ? esc_attr( $options['marketplace'] ) : ''; ?>
						<p class="description">
							Identificador da conta que vai receber comissões de todas as vendas na plataforma. Após ativar o plugin, se estiver vazio, reconecte ao PagSeguro via aba Integração.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_pagseguro_marketplace">
						Identificar Vendedor
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>Identificar Seller</span></legend>
						<input class="input-text regular-input"
							type="text"
							name="woocommerce_virt_pagseguro_seller_identifier"
							id="woocommerce_virt_pagseguro_seller_identifier"
							placeholder="Vendido e entregue por {NOMESELLER}"
							value="<?php echo isset( $options['seller_identifier'] ) ? esc_attr( $options['seller_identifier'] ) : ''; ?>">
						<p class="description">
							Define o Texto que identifica o seller na página do produto da loja. Use {NOMESELLER} para referenciar o seller do produto. Deixe em branco para desativar.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_pagseguro_display_shipping">
						Ocultar para o Seller
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<ul class="sources">
							<li class="source">
								<input type="checkbox"
									name="woocommerce_virt_pagseguro_hide_shipping_method"
									id="woocommerce_virt_pagseguro_hide_shipping_method"
									<?php checked( 'yes', $options['hide_shipping_method'] ); ?>
									value="yes">
								<label for="woocommerce_virt_pagseguro_hide_shipping_method">
									Método de entrega
								</label>
							</li>
							<li class="source">
								<input type="checkbox"
									name="woocommerce_virt_pagseguro_hide_cpf"
									id="woocommerce_virt_pagseguro_hide_cpf"
									<?php checked( 'yes', $options['hide_cpf'] ); ?>
									value="yes">
								<label for="woocommerce_virt_pagseguro_hide_cpf">
									CPF
								</label>
							</li>
							<li class="source">
								<input type="checkbox"
									name="woocommerce_virt_pagseguro_hide_address"
									id="woocommerce_virt_pagseguro_hide_address"
									<?php checked( 'yes', $options['hide_address'] ); ?>
									value="yes">
								<label for="woocommerce_virt_pagseguro_hide_address">
									Endereço e informações de contato
								</label>
							</li>
							<li class="source">
								<input type="checkbox"
									name="woocommerce_virt_pagseguro_hide_coupons"
									id="woocommerce_virt_pagseguro_hide_coupons"
									<?php checked( 'yes', $options['hide_coupons'] ); ?>
									value="yes">
								<label for="woocommerce_virt_pagseguro_hide_coupons">
									Cupons de desconto
								</label>
							</li>
						</ul>

						<p class="description">
							Marque para definir quais informações não estarão disponíveis para os sellers.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="woocommerce_virt_pagseguro_display_shipping">
						Ocultar na loja:
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<ul class="sources">
							<li class="source">
								<input type="checkbox"
									name="woocommerce_virt_pagseguro_hide_unpurchasable_products"
									id="woocommerce_virt_pagseguro_hide_unpurchasable_products"
									<?php checked( 'yes', $options['hide_unpurchasable_products'] ); ?>
									value="yes">
								<label for="woocommerce_virt_pagseguro_hide_unpurchasable_products">
									Produtos de Seller com status inativo
								</label>
							</li>
							<li class="source">
								<input type="checkbox"
									name="woocommerce_virt_pagseguro_hide_reputation"
									id="woocommerce_virt_pagseguro_hide_reputation"
									<?php checked( 'yes', $options['hide_reputation'] ); ?>
									value="yes">
								<label for="woocommerce_virt_pagseguro_hide_reputation">
									Reputação ( página do Seller )
								</label>
							</li>
							<li class="source">
								<input type="checkbox"
									name="woocommerce_virt_pagseguro_hide_total_sales"
									id="woocommerce_virt_pagseguro_hide_total_sales"
									<?php checked( 'yes', $options['hide_total_sales'] ); ?>
									value="yes">
								<label for="woocommerce_virt_pagseguro_hide_total_sales">
									Total de vendas  ( página do Seller )
								</label>
							</li>
						</ul>

						<p class="description">
							Marque para definir quais informações não estarão disponíveis na área aberta da loja.
						</p>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row" class="titledesc">Observação</th>
				<td class="forminp">
					<p class="description">
						No momento, os seguintes recursos do plugin Virtuaria PagSeguro não estão disponíveis para uso com Split ativado:
					</p>
					<ol>
						<li>Rembolso Parcial</li>
						<li>Cobrança adicional</li>
						<li>Desconto do Pix</li>
					</ol>
					<small style="font-style: italic;color:#646970;">
						A medida que a API de Split do PagSeguro for evoluindo, estes recursos serão incorporados ao Plugin Virtuaria com Split ativado.
					</small>
				</td>
			</tr>
		</tbody>
	</table>
	<button name="save_split"
		class="button-primary woocommerce-save-button"
		type="submit"
		value="Salvar alterações">Salvar alterações</button>
	<?php wp_nonce_field( 'setup_virtuaria_split', 'setup_nonce' ); ?>
</form>
