<?php

namespace Drupal\Tests\update\Functional;

/**
 * Tests the Update Manager module with a contrib module with semver versions.
 *
 * @group update
 */
class UpdateSemverContribTest extends UpdateSemverTestBase {
  use UpdateTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $updateTableLocator = 'table.update:nth-of-type(2)';

  /**
   * {@inheritdoc}
   */
  protected $updateProject = 'semver_test';

  /**
   * {@inheritdoc}
   */
  protected $projectTitle = 'Semver Test';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['semver_test'];

  /**
   * {@inheritdoc}
   */
  protected function setProjectInstalledVersion($version) {
    $this->mockInstalledExtensionsInfo([
      $this->updateProject => [
        'project' => $this->updateProject,
        'version' => $version,
        'hidden' => FALSE,
      ],
      // Ensure Drupal core on the same version for all test runs.
      'drupal' => [
        'project' => 'drupal',
        'version' => '8.0.0',
        'hidden' => FALSE,
      ],
    ]);
    $this->mockDefaultExtensionsInfo(['version' => '8.0.0']);
  }

}
