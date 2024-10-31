<?php if ( !defined( 'ABSPATH' ) ) exit;


$fields = array(
    'blik0_code' => array(
        'label' => __( 'Type your BLIK code', 'wc-gateway-paylane' ),
        'required' => true,
        'type' => 'text',
        'class' => array('form-row-wide'),
        'validate' => array(),
        'autocomplete' => 'no',
        'priority' => 100,
        'placeholder' => '123 456',
    ),
);

?>
<div class="paylane-payment-form paylane-payment-form--blik0">
    <?php

    foreach ( $fields as $key => $field ) {
      ?>

      <div class="paylane-payment-form__field">

      <?php
        woocommerce_form_field( $key, $field );
      ?>
        <div class="paylane-payment-form__error-message" data-paylane-error-message="<?php echo esc_attr($key) ?>"></div>
      </div>

      <?php
    }
    ?>

  <div class="paylane-payment-form__error-message" data-paylane-error-message="blik0"></div>
</div>

