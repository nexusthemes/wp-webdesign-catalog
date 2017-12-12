<?php
function wdc_customize_register($wp_customize)
{
  $wp_customize->add_section
  (
  	'wdc_item_section', 
  	array
  	(
      'title'    => 'Item Template',
      'priority' => 120,
  	)
  );
  
  
  //  =============================
  //  = Text Input                =
  //  =============================
  $wp_customize->add_setting
  (
  	'wdc_plugin_options[item_template]', array
  	(
      'default'        => '<h1>{{title}}</h1>',
      'capability'     => 'edit_theme_options',
      'type'           => 'option',
  	)
  );
  
  $wp_customize->add_control
  (
  	'wdc_item_template', 
  	array
  	(
  		'type' => 'textarea',
      'label'      => 'Title Template',
      'section'    => 'wdc_item_section',
      'settings'   => 'wdc_plugin_options[item_template]',
    )
  );
  
  //
  // cta button
  //
  
  $wp_customize->add_setting
  (
  	'wdc_plugin_options[detail_cta_button]', array
  	(
      'default'        => 'Use this design',
      'capability'     => 'edit_theme_options',
      'type'           => 'option',
  	)
  );
  
  $wp_customize->add_control
  (
  	'wdc_item_template_2', 
  	array
  	(
  		'type' => 'text',
      'label'      => 'Detail Call To Action Button',
      'section'    => 'wdc_item_section',
      'settings'   => 'wdc_plugin_options[detail_cta_button]',
    )
  );
  
  //
  //
  //
  
  $wp_customize->add_setting
  (
  	'wdc_plugin_options[detail_cta_proceedurl]', array
  	(
      'default'        => '',
      'capability'     => 'edit_theme_options',
      'type'           => 'option',
  	)
  );
  
  $wp_customize->add_control
  (
  	'wdc_item_template_3', 
  	array
  	(
  		'type' => 'text',
      'label'      => 'Detail Call To Action Return URL',
      'section'    => 'wdc_item_section',
      'settings'   => 'wdc_plugin_options[detail_cta_proceedurl]',
    )
  );
  
  //
  // back button
  //
  
  $wp_customize->add_setting
  (
  	'wdc_plugin_options[detail_back_button]', array
  	(
      'default'        => '',
      'capability'     => 'edit_theme_options',
      'type'           => 'option',
  	)
  );
  
  $wp_customize->add_control
  (
  	'wdc_item_template_4', 
  	array
  	(
  		'type' => 'text',
      'label'      => 'Detail Back Button',
      'section'    => 'wdc_item_section',
      'settings'   => 'wdc_plugin_options[detail_back_button]',
    )
  );
  
  //
  //
  //
  
}
add_action('customize_register', 'wdc_customize_register');