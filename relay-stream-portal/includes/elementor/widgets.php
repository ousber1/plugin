<?php
/**
 * Elementor widgets. Every widget delegates to RSP_Portal (the same renderer as the shortcodes),
 * so data, permissions and ownership checks are identical. Widget settings only hold presentation
 * (layout, labels, colors, typography, spacing) — never customer data, prices or API settings.
 *
 * Style precedence: WordPress dashboard Branding settings are the defaults (CSS variables on
 * .rsp-root); any Style control set on a widget overrides those variables for that widget only.
 *
 * @package RelayStreamPortal
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

/**
 * Base widget.
 */
abstract class RSP_Widget_Base extends Widget_Base {

	/**
	 * Definition key.
	 *
	 * @return string
	 */
	abstract protected function key();

	/**
	 * Definition.
	 *
	 * @return array
	 */
	protected function def() {
		$d = RSP_Elementor::definitions();
		return $d[ $this->key() ];
	}

	/** @return string */
	public function get_name() {
		return $this->def()[0];
	}

	/** @return string */
	public function get_title() {
		return $this->def()[1];
	}

	/** @return string */
	public function get_icon() {
		return $this->def()[3];
	}

	/** @return string[] */
	public function get_categories() {
		return array( RSP_Elementor::CATEGORY );
	}

	/** @return string[] */
	public function get_keywords() {
		return array( 'relay', 'portal', 'stream', 'subscription', $this->module() );
	}

	/** @return string[] */
	public function get_style_depends() {
		return array( 'rsp-portal' );
	}

	/** @return string[] */
	public function get_script_depends() {
		return array( 'rsp-portal' );
	}

	/**
	 * Render on the server only (no JS template), so nothing live is serialized into the editor.
	 *
	 * @return bool
	 */
	public function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * Module slug.
	 *
	 * @return string
	 */
	protected function module() {
		return $this->def()[2];
	}

	/**
	 * Which label controls this widget offers.
	 *
	 * @return array key => default label
	 */
	protected function label_controls() {
		$m = $this->module();
		if ( in_array( $m, array( 'support_button' ), true ) ) {
			return array( 'button' => __( 'Contact support', 'relay-stream-portal' ) );
		}
		if ( in_array( $m, array( 'plan_cards', 'login', 'register' ), true ) ) {
			return array( 'title' => '', 'button' => '' );
		}
		if ( 'dashboard' === $m ) {
			return array( 'welcome' => '' );
		}
		if ( in_array( $m, array( 'announcements', 'device_guides' ), true ) ) {
			return array();
		}
		return array( 'title' => '' );
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section( 'rsp_content', array( 'label' => __( 'Content', 'relay-stream-portal' ) ) );

		$this->add_control(
			'rsp_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'The editor shows sample data. Visitors see their own data after signing in. Plans, prices and accounts come from WordPress/WooCommerce settings.', 'relay-stream-portal' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		if ( 'shell' === $this->module() ) {
			$mods = RSP_Settings::modules();
			$this->add_control(
				'rsp_module',
				array(
					'label'   => __( 'Default module', 'relay-stream-portal' ),
					'type'    => Controls_Manager::SELECT,
					'default' => 'dashboard',
					'options' => $mods,
				)
			);
			foreach ( $mods as $slug => $label ) {
				$this->add_control( 'rsp_label_nav_' . $slug, array( 'label' => sprintf( /* translators: %s module */ __( 'Menu label: %s', 'relay-stream-portal' ), $label ), 'type' => Controls_Manager::TEXT, 'placeholder' => $label ) );
			}
		}
		if ( 'device_guides' === $this->module() ) {
			$this->add_control( 'rsp_device', array( 'label' => __( 'Only this device ID (optional)', 'relay-stream-portal' ), 'type' => Controls_Manager::TEXT, 'placeholder' => 'firetv' ) );
		}
		foreach ( $this->label_controls() as $k => $default ) {
			$this->add_control(
				'rsp_label_' . $k,
				array(
					/* translators: %s label key */
					'label'       => 'welcome' === $k ? __( 'Welcome text ({name} = first name)', 'relay-stream-portal' ) : ( 'button' === $k ? __( 'Button label', 'relay-stream-portal' ) : __( 'Title', 'relay-stream-portal' ) ),
					'type'        => Controls_Manager::TEXT,
					'placeholder' => $default ? $default : __( 'Default', 'relay-stream-portal' ),
				)
			);
		}
		$this->end_controls_section();

		$this->start_controls_section( 'rsp_layout', array( 'label' => __( 'Layout', 'relay-stream-portal' ) ) );
		$this->add_control(
			'rsp_fullbleed',
			array(
				'label'        => __( 'Stretch to full browser width', 'relay-stream-portal' ),
				'type'         => Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$this->add_responsive_control(
			'rsp_max',
			array(
				'label'      => __( 'Content max width', 'relay-stream-portal' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array( 'px' => array( 'min' => 320, 'max' => 1800 ) ),
				'selectors'  => array( '{{WRAPPER}} .rsp-root' => '--rsp-max: {{SIZE}}{{UNIT}};' ),
			)
		);
		$this->add_control(
			'rsp_theme',
			array(
				'label'   => __( 'Color theme', 'relay-stream-portal' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array( '' => __( 'Use portal setting', 'relay-stream-portal' ), 'dark' => __( 'Dark', 'relay-stream-portal' ), 'light' => __( 'Light', 'relay-stream-portal' ), 'auto' => __( 'Follow device', 'relay-stream-portal' ) ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section( 'rsp_style_colors', array( 'label' => __( 'Colors', 'relay-stream-portal' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$vars = array(
			'accent'   => array( __( 'Accent', 'relay-stream-portal' ), '--rsp-accent' ),
			'accent2'  => array( __( 'Secondary accent', 'relay-stream-portal' ), '--rsp-accent-2' ),
			'bg'       => array( __( 'Background', 'relay-stream-portal' ), '--rsp-bg' ),
			'surface'  => array( __( 'Card background', 'relay-stream-portal' ), '--rsp-surface' ),
			'surface2' => array( __( 'Input / inset background', 'relay-stream-portal' ), '--rsp-surface-2' ),
			'text'     => array( __( 'Text', 'relay-stream-portal' ), '--rsp-text' ),
			'muted'    => array( __( 'Secondary text', 'relay-stream-portal' ), '--rsp-muted' ),
			'border'   => array( __( 'Borders', 'relay-stream-portal' ), '--rsp-border' ),
		);
		foreach ( $vars as $k => $v ) {
			$this->add_control(
				'rsp_color_' . $k,
				array(
					'label'     => $v[0],
					'type'      => Controls_Manager::COLOR,
					'selectors' => array( '{{WRAPPER}} .rsp-root, {{WRAPPER}} .rsp-root[data-rsp-theme]' => $v[1] . ': {{VALUE}};' ),
				)
			);
		}
		$this->end_controls_section();

		$this->start_controls_section( 'rsp_style_type', array( 'label' => __( 'Typography & spacing', 'relay-stream-portal' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'rsp_body_type', 'label' => __( 'Body', 'relay-stream-portal' ), 'selector' => '{{WRAPPER}} .rsp-root' ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'rsp_head_type', 'label' => __( 'Headings', 'relay-stream-portal' ), 'selector' => '{{WRAPPER}} .rsp-root h1, {{WRAPPER}} .rsp-root h2, {{WRAPPER}} .rsp-root h3' ) );
		$this->add_control(
			'rsp_radius',
			array(
				'label'     => __( 'Corner radius', 'relay-stream-portal' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 0, 'max' => 32 ) ),
				'selectors' => array( '{{WRAPPER}} .rsp-root' => '--rsp-radius: {{SIZE}}px;' ),
			)
		);
		$this->add_control(
			'rsp_gap',
			array(
				'label'     => __( 'Spacing between blocks', 'relay-stream-portal' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => array( 'px' => array( 'min' => 4, 'max' => 48 ) ),
				'selectors' => array( '{{WRAPPER}} .rsp-root' => '--rsp-gap: {{SIZE}}px;' ),
			)
		);
		$this->add_responsive_control(
			'rsp_padding',
			array(
				'label'      => __( 'Inner padding', 'relay-stream-portal' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array( '{{WRAPPER}} .rsp-root > .rsp-main, {{WRAPPER}} .rsp-embed' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			)
		);
		$this->end_controls_section();
	}

	/**
	 * Render (server side).
	 */
	protected function render() {
		$s      = $this->get_settings_for_display();
		$labels = array();
		foreach ( $s as $k => $v ) {
			if ( 0 === strpos( $k, 'rsp_label_' ) && is_string( $v ) && '' !== trim( $v ) ) {
				$labels[ substr( $k, 10 ) ] = sanitize_text_field( $v );
			}
		}
		$args = array(
			'labels'    => $labels,
			'fullbleed' => ! empty( $s['rsp_fullbleed'] ) && 'yes' === $s['rsp_fullbleed'],
			'theme'     => isset( $s['rsp_theme'] ) ? sanitize_key( $s['rsp_theme'] ) : '',
			'device'    => isset( $s['rsp_device'] ) ? sanitize_key( $s['rsp_device'] ) : '',
		);
		if ( 'shell' === $this->module() ) {
			$args['module'] = isset( $s['rsp_module'] ) ? sanitize_key( $s['rsp_module'] ) : 'dashboard';
			echo RSP_Portal::render_shell( $args ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in templates.
			return;
		}
		echo RSP_Portal::render_module( $this->module(), $args ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

// phpcs:disable Generic.Files.OneObjectStructurePerFile
/** Portal shell. */
class RSP_Widget_Shell extends RSP_Widget_Base { protected function key() { return 'Shell'; } }
/** Overview. */
class RSP_Widget_Dashboard extends RSP_Widget_Base { protected function key() { return 'Dashboard'; } }
/** Accounts. */
class RSP_Widget_Accounts extends RSP_Widget_Base { protected function key() { return 'Accounts'; } }
/** Apps. */
class RSP_Widget_Apps extends RSP_Widget_Base { protected function key() { return 'Apps'; } }
/** Plans. */
class RSP_Widget_Plans extends RSP_Widget_Base { protected function key() { return 'Plans'; } }
/** Transactions. */
class RSP_Widget_Transactions extends RSP_Widget_Base { protected function key() { return 'Transactions'; } }
/** Referrals. */
class RSP_Widget_Referrals extends RSP_Widget_Base { protected function key() { return 'Referrals'; } }
/** Redeem. */
class RSP_Widget_Redeem extends RSP_Widget_Base { protected function key() { return 'Redeem'; } }
/** Support. */
class RSP_Widget_Support extends RSP_Widget_Base { protected function key() { return 'Support'; } }
/** Profile. */
class RSP_Widget_Profile extends RSP_Widget_Base { protected function key() { return 'Profile'; } }
/** Login. */
class RSP_Widget_Login extends RSP_Widget_Base { protected function key() { return 'Login'; } }
/** Register. */
class RSP_Widget_Register extends RSP_Widget_Base { protected function key() { return 'Register'; } }
/** Reset. */
class RSP_Widget_Reset extends RSP_Widget_Base { protected function key() { return 'Reset'; } }
/** Plan cards. */
class RSP_Widget_Plan_Cards extends RSP_Widget_Base { protected function key() { return 'Plan_Cards'; } }
/** Summary. */
class RSP_Widget_Summary extends RSP_Widget_Base { protected function key() { return 'Summary'; } }
/** Announcements. */
class RSP_Widget_Announcements extends RSP_Widget_Base { protected function key() { return 'Announcements'; } }
/** Device guides. */
class RSP_Widget_Device_Guides extends RSP_Widget_Base { protected function key() { return 'Device_Guides'; } }
/** Support button. */
class RSP_Widget_Support_Button extends RSP_Widget_Base { protected function key() { return 'Support_Button'; } }
