<?php

namespace Drupal\system\Form;

use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure development settings for this site.
 *
 * @internal
 */
class DevelopmentSettingsForm extends FormBase {

  /**
   * Constructs a new development settings form.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\DrupalKernelInterface $kernel
   *   The Drupal kernel.
   */
  public function __construct(
    protected StateInterface $state,
    protected DrupalKernelInterface $kernel
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static(
      $container->get('state'),
      $container->get('kernel')
    );
    $instance->setMessenger($container->get('messenger'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'development_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // System aggregation setting.
    $directory = 'assets://';
    $is_writable = is_dir($directory) && is_writable($directory);
    $disabled = !$is_writable;
    $disabled_message = '';
    if (!$is_writable) {
      $disabled_message = ' ' . $this->t('<strong class="error">Set up the <a href=":file-system">optimized assets file system path</a> to make these optimizations available.</strong>', [':file-system' => Url::fromRoute('system.file_system_settings')->toString()]);
    }
    $system_performance = \Drupal::config('system.performance');
    $performance_css_config = $system_performance->get('css.preprocess');
    $performance_js_config = $system_performance->get('js.preprocess');

    $form['description'] = [
      '#plain_text' => $this->t('These settings should only be enabled on development environments and never on production.'),
    ];
    $performance_config = \Drupal::config('system.performance');
    $twig_debug = $this->state->get('twig_debug', FALSE);
    $twig_cache_disable = $this->state->get('twig_cache_disable', FALSE);
    $twig_development_state_conditions = [
      'input[data-drupal-selector="edit-twig-development-mode"]' => [
        'checked' => TRUE,
      ],
    ];
    $form['twig_development_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Twig development mode'),
      '#description' => $this->t('Exposes Twig development settings.'),
      '#default_value' => $twig_debug || $twig_cache_disable,
    ];
    $form['twig_development'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Twig development mode'),
      '#states' => [
        'visible' => $twig_development_state_conditions,
      ],
    ];
    $form['twig_development']['twig_debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Twig debug mode'),
      '#description' => $this->t("Provides Twig's <code>dump()</code> function for debugging, outputs template suggestions to HTML comments, and automatically recompile Twig templates after changes."),
      '#default_value' => $twig_debug,
    ];
    $form['twig_development']['twig_cache_disable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable Twig cache'),
      '#description' => $this->t('Twig templates are not cached and are always compiled when rendered.'),
      '#default_value' => $twig_cache_disable,
    ];
    if (!$twig_debug && !$twig_cache_disable) {
      $form['twig_development']['twig_debug']['#states'] = [
        'checked' => $twig_development_state_conditions,
      ];
      $form['twig_development']['twig_cache_disable']['#states'] = [
        'checked' => $twig_development_state_conditions,
      ];
    }

    $form['disable_rendered_output_cache_bins'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not cache markup'),
      '#description' => $this->t('Disables render cache, dynamic page cache, and page cache.'),
      '#default_value' => $this->state->get('disable_rendered_output_cache_bins', FALSE),
    ];

    $form['bandwidth_optimization'] = [
      '#type' => 'details',
      '#title' => $this->t('Bandwidth optimization'),
      '#open' => TRUE,
      '#description' => $this->t('External resources can be optimized automatically, which can reduce both the size and number of requests made to your website.') . $disabled_message,
    ];
    $form['bandwidth_optimization']['preprocess_css'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Aggregate CSS files'),
      '#default_value' => $performance_css_config,
      '#disabled' => $disabled,
    ];
    $form['bandwidth_optimization']['preprocess_js'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Aggregate JavaScript files'),
      '#default_value' => $performance_js_config,
      '#disabled' => $disabled,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save settings'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $disable_rendered_output_cache_bins_previous = $this->state->get('disable_rendered_output_cache_bins', FALSE);
    $disable_rendered_output_cache_bins = (bool) $form_state->getValue('disable_rendered_output_cache_bins');
    if ($disable_rendered_output_cache_bins) {
      $this->state->set('disable_rendered_output_cache_bins', TRUE);
    }
    else {
      $this->state->delete('disable_rendered_output_cache_bins');
    }
    $twig_development_mode = (bool) $form_state->getValue('twig_development_mode');
    $twig_development_previous = $this->state->getMultiple(['twig_debug', 'twig_cache_disable']);
    $twig_development = [
      'twig_debug' => (bool) $form_state->getValue('twig_debug'),
      'twig_cache_disable' => (bool) $form_state->getValue('twig_cache_disable'),
    ];
    if ($twig_development_mode) {
      $invalidate_container = $twig_development_previous !== $twig_development;
      $this->state->setMultiple($twig_development);
    }
    else {
      $invalidate_container = TRUE;
      $this->state->deleteMultiple(array_keys($twig_development));
    }

    if ($invalidate_container || $disable_rendered_output_cache_bins_previous !== $disable_rendered_output_cache_bins) {
      $this->kernel->invalidateContainer();
    }
    // Saved system performance aggregation configuration.
    $performance_css_config = $form_state->getValue('preprocess_css');
    $performance_js_config = $form_state->getValue('preprocess_js');
    \Drupal::configFactory()->getEditable('system.performance')
      ->set('css.preprocess', $performance_css_config)
      ->set('js.preprocess', $performance_js_config)
      ->save();

    $this->messenger()->addStatus($this->t('The settings have been saved.'));
  }

}
