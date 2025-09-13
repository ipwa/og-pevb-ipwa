<?php

declare(strict_types=1);

namespace Drupal\server_general\ThemeTrait;

use Drupal\server_general\ThemeTrait\Enum\AlignmentEnum;
use Drupal\server_general\ThemeTrait\Enum\FontSizeEnum;
use Drupal\server_general\ThemeTrait\Enum\FontWeightEnum;
use Drupal\server_general\ThemeTrait\Enum\TextColorEnum;

/**
 * Helper methods for rendering People/Person Teaser elements.
 */
trait PersonCardsThemeTrait {

  use ElementLayoutThemeTrait;
  use ElementWrapThemeTrait;
  use InnerElementLayoutThemeTrait;
  use CardThemeTrait;

  /**
   * Build People cards element.
   *
   * @param string $title
   *   The title.
   * @param array $body
   *   The body render array.
   * @param array $items
   *   The render array built with
   *   `ElementLayoutThemeTrait::buildElementLayoutTitleBodyAndItems`.
   *
   * @return array
   *   The render array.
   */
  protected function buildElementPersonCards(string $title, array $body, array $items): array {
    return $this->buildElementLayoutTitleBodyAndItems(
      $title,
      $body,
      $this->buildCards($items),
    );
  }

  /**
   * Build a Person card.
   *
   * @param string $image_url
   *   The image Url.
   * @param string $alt
   *   The image alt.
   * @param string $name
   *   The name.
   * @param string|null $subtitle
   *   Optional; The subtitle (e.g. work title).
   *
   * @return array
   *   The render array.
   */

  protected function buildElementPersonCard(
    string $image_url,
    string $alt,
    string $name,
    ?string $subtitle = null,
    ?string $role = null,
    ?string $email = null,
    ?string $phone = null
  ): array {
    $elements = [];

    // Image element
    $element = [
      '#theme' => 'image',
      '#uri' => $image_url,
      '#alt' => $alt,
      '#width' => 128,
    ];
    $elements[] = $this->wrapRoundedCornersFull($element);

    $inner_elements = [];
    $cta_elements = [];

    // Name
    $element = $this->wrapTextFontWeight($name, FontWeightEnum::Bold);
    $inner_elements[] = $this->wrapTextCenter($element);

    // Optional subtitle
    if (!empty($subtitle)) {
      $element = $this->wrapTextResponsiveFontSize($subtitle, FontSizeEnum::Sm);
      $element = $this->wrapTextCenter($element);
      $inner_elements[] = $this->wrapTextColor($element, TextColorEnum::Gray);
    }

    // Optional role
    if (!empty($role)) {
      $inner_elements[] = $this->wrapTextPill($role);
    }

    // Optional email as mailto link
    if (!empty($email)) {
      $email_element = [
        '#type' => 'link',
        '#theme' => 'server_theme_card_button',
        '#title' => 'Email',
        '#url' => \Drupal\Core\Url::fromUri('mailto:' . $email),
        '#button_type' => 'mail',
        '#attributes' => ['class' => ['person-card-email']],
      ];
      $cta_elements[] = $email_element;
    }

    // Optional phone as tel link
    if (!empty($phone)) {
      $phone_element = [
        '#type' => 'link',
        '#theme' => 'server_theme_card_button',
        '#title' => 'Call',
        '#url' => \Drupal\Core\Url::fromUri('tel:' . $phone),
        '#button_type' => 'phone',
        '#attributes' => ['class' => ['person-card-phone']],
      ];
      $cta_elements[] = $phone_element;
    }

    // Optional role
    if (!empty($email || $phone)) {
      $inner_elements[] = $this->wrapButtonsInline($cta_elements);
    }

    $elements[] = $this->wrapContainerVerticalSpacingCards($inner_elements);

    return $this->buildInnerElementLayoutCentered($elements);
  }

}
