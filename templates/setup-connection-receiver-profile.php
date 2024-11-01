<?php
/**
 * Display recipient profile connection.
 *
 * @package virtuaria/pagseguro/split
 */

defined( 'ABSPATH' ) || exit;

$options   = get_option( 'woocommerce_virt_pagseguro_settings' );
$recipient = get_user_meta(
	$user->ID,
	'_virtuaria_receiver',
	true
);

if ( isset( $options['environment'] ) && 'sandbox' === $options['environment'] ) {
	$app_id     = 'a2c55b69-d66f-4bf0-80f9-21d504ebf559';
	$app_url    = 'pagseguro.virtuaria.com.br/auth/pagseguro-sandbox';
	$app_revoke = 'https://pagseguro.virtuaria.com.br/revoke/pagseguro-sandbox';
	$token      = isset( $recipient['token_sanbox'] ) ? $recipient['token_sanbox'] : '';
	$fee_setup  = '';
} else {
	$fee_setup = isset( $options['fee_setup'] ) ? $options['fee_setup'] : '';

	if ( 'd14' === $fee_setup ) {
		$app_id = 'f7aa07e1-5368-45cd-9372-67db6777b4b0';
	} elseif ( 'd30' === $fee_setup ) {
		$app_id = 'a59bb94a-2e78-43bc-a497-30447bdf1a3e';
	} else {
		$app_id = '7acbe665-76c3-4312-afd5-29c263e8fb93';
	}
	$app_url    = 'pagseguro.virtuaria.com.br/auth/pagseguro';
	$app_revoke = 'https://pagseguro.virtuaria.com.br/revoke/pagseguro';
	$token      = isset( $recipient['token_production'] ) ? $recipient['token_production'] : '';

	$options['environment'] = 'production';
}

if ( isset( $_GET['token'] ) ) {
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_attr_e( 'Conta Conectada!', 'virtuaria-pagbank-split' ); ?></p>
	</div>
	<?php
} elseif ( isset( $_GET['proccess'] ) ) {
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_attr_e( 'Falha ao processar operação!', 'virtuaria-pagbank-split' ); ?></p>
	</div>
	<?php
} elseif ( isset( $_GET['access_revoked'] ) ) {
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_attr_e( 'Conta Desconectada!', 'virtuaria-pagbank-split' ); ?></p>
	</div>
	<?php
}

?>
<hr>
<h3 class="virtuaria-recipient">Definições do Seller</h3>
<table class="form-table">
	<tr>
		<th>
			<label for="recipient-fee">Taxa Padrão</label>
		</th>
		<td style="font-weight: bold;color:blue">
			<?php
			if ( isset( $recipient['fee'] ) && $recipient['fee'] ) {
				echo esc_attr( $recipient['fee'] ) . '%';
			} elseif ( $global_fee ) {
				echo esc_html( $global_fee ) . '%';
			} else {
				echo 'A definir';
			}

			do_action( 'virtuaria_recipient_fee_text', $recipient, $global_fee );
			?>
		</td>
	</tr>
	<tr valign="top" class="virtuaria-connect">
		<th scope="row" class="titledesc">
			<label for="woocommerce_virt_pagseguro_autorization">
				Autorização <span class="woocommerce-help-tip"></span>
			</label>
		</th>
		<td class="forminp forminp-auth">
			<?php
			$auth = '';
			if ( 'sandbox' === $options['environment'] ) {
				$auth = 'sandbox.';
			}

			$origin = str_replace( array( 'https://', 'http://' ), '', home_url() );

			$auth  = 'https://connect.' . $auth . 'pagseguro.uol.com.br/oauth2/authorize';
			$auth .= '?response_type=code&client_id=' . $app_id . '&redirect_uri=' . $app_url;
			$auth .= '&scope=payments.read+payments.create+payments.refund+accounts.read';
			if ( class_exists( 'Virtuaria_PagBank_Split' ) ) {
				$auth .= '+payments.split.read';
			}
			$auth .= '&state=' . $origin;
			if ( $fee_setup ) {
				$auth .= '--' . $fee_setup;
			}
			$mail  = str_replace(
				'@',
				'aN',
				wp_get_current_user()->user_email
			) . 'aNsellersplittt';
			$auth .= '--' . $mail;

			if ( 'sandbox' !== $options['environment'] ) {
				if ( $token ) {
					$revoke_url = $app_revoke . '?state=' . $origin . ( $fee_setup ? '--' . $fee_setup : '' ) . '--' . $mail . $recipient['account'];
					echo '<span class="connected"><strong>Status: <span class="status">Conectado.</span></strong></span>';
					echo '<a href="' . esc_url( $revoke_url ) . '" class="auth button-primary">Desconectar com PagSeguro <img src="'
					. esc_url( VIRTUARIA_PAGSEGURO_URL ) . 'public/images/conectado.svg" alt="Desconectar" /></a>';
				} else {
					echo '<span class="disconnected"><strong>Status: <span class="status">Desconectado.</span></strong></span>';
					echo '<a href="' . esc_url( $auth ) . '" class="auth button-primary">Conectar com PagSeguro <img src="'
					. esc_url( VIRTUARIA_PAGSEGURO_URL ) . 'public/images/conectar.png" alt="Conectar" /></a>';
				}
				echo '<span class="expire-info">Conecte-se ao PagBank para receber os valores referentes à venda dos seus produtos.</span>';
			} else {
				echo '<p class="sandbox-warning">Devido às limitações da Sandbox, não é necessário realizar a conexão do Seller a Sandbox. Na loja virtual, o split ocorrerá normalmente.</p>';
			}
			?>
		</td>
	</tr>
	<tr valign="top" class="shop-page">
		<th scope="row" class="titledesc">
			<label for="shop-page">
				Lista de Produtos
			</label>
		</th>
		<td class="forminp forminp-auth">
			<?php
			$seller_page = home_url( 'seller/' . wp_get_current_user()->user_login );
			printf(
				'<a href="%s" target="_blank">%s</a>',
				esc_url( $seller_page ),
				esc_url( $seller_page )
			);
			?>
		</td>
	</tr>
</table>
<hr>
<style>
	#your-profile h3.virtuaria-recipient {
		display: block;
	}
	#your-profile .form-table:nth-of-type(6) tr:last-of-type,
	#your-profile table.form-table:nth-of-type(6) .virtuaria-connect {
		display: table-row;
	}
	.expire-info {
		display: block;
	}
	.forminp-auth .auth {
		display: inline-block;
		vertical-align: middle;
		margin-left: 20px;
		margin-bottom: 10px;
		padding: 5px 10px;
	}
	.auth img {
		display: inline-block;
		vertical-align: middle;
		max-width: 35px;
	}
	.user-url-wrap,
	#your-profile h2:nth-of-type(4) {
		display: none;
	}
	#application-passwords-section,
	#your-profile .form-table:first-of-type {
		display: none;
	}
</style>
