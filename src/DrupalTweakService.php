<?php

namespace Drupal\drupal_tweak;

use Doctrine\Common\Inflector\Inflector;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkTree;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\field\Entity\FieldConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Html2Text\Html2Text;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Webmozart\PathUtil\Path;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Theme\ThemeManager;
use Drupal\views\Views;

/**
 * Class DrupalTweakService.
 *
 * Usage - basic:
 *   \Drupal::service('drupal.tweak')->methodName($arg);
 *
 * Usage - IDE autocomplete method names.
 *   ** @var \Drupal\drupal_tweak\DrupalTweakService $drupal * /
 *   $drupal = \Drupal::service('drupal.tweak');
 *   $drupal->methodNameNowAutocompletes();
 */
class DrupalTweakService {

  /**
   * @var array
   */
  protected $entityTypes;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack $requestStack
   */
  protected $requestStack;

  /**
   * @var \Drupal\Core\Entity\Query\QueryFactory $entityQuery
   */
  protected $entityQuery;

  /**
   * @var \Drupal\Core\Session\AccountInterface $currentUser
   */
  protected $currentUser;

  /**
   * @var \Drupal\Core\Routing\RouteMatch
   */
  protected $currentRouteMatch;

  /**
   * @var \Drupal\Core\Entity\EntityFormBuilder
   */
  protected $entityFormBuilder;

  /**
   * @var \Drupal\Core\Menu\MenuLinkTree
   */
  protected $menuLinkTree;

  /**
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Theme\ThemeManager
   */
  protected $themeManager;

  /**
   * DrupalTweakService constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   * @param \Drupal\Core\Entity\Query\QueryFactory $entityQuery
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   * @param \Drupal\Core\Entity\EntityFormBuilder $entityFormBuilder
   * @param \Drupal\Core\Menu\MenuLinkTree $menuLinkTree
   * @param \Drupal\Core\Render\Renderer $renderer
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   * @param \Drupal\Core\Theme\ThemeManager $themeManager
   */
  public function __construct(RequestStack $requestStack, QueryFactory $entityQuery, AccountInterface $currentUser, CurrentRouteMatch $currentRouteMatch, EntityFormBuilder $entityFormBuilder, MenuLinkTree $menuLinkTree, Renderer $renderer, EntityTypeManager $entityTypeManager, BlockManagerInterface $block_manager, FormBuilder $formBuilder, ConfigFactory $configFactory, ThemeManager $themeManager) {

    $this->entityTypes = ['node', 'taxonomy', 'media'];

    $this->requestStack = $requestStack;
    $this->entityQuery = $entityQuery;
    $this->currentUser = $currentUser;
    $this->currentRouteMatch = $currentRouteMatch;
    $this->entityFormBuilder = $entityFormBuilder;
    $this->menuLinkTree = $menuLinkTree;
    $this->renderer = $renderer;
    $this->entityTypeManager = $entityTypeManager;
    $this->blockManager = $block_manager;
    $this->formBuilder = $formBuilder;
    $this->configFactory = $configFactory;
    $this->themeManager = $themeManager;
  }

  /**
   * Turns a string, or an array of strings, into pluralised version of that string.
   *
   * Example usage:
   *  \Drupal::service('drupal.tweak)->pluralise('canopy'); // Returns "canopies"
   *
   * @param $string
   *   Can be a single string, or an array of strings.
   *
   * @return array|bool|string
   *   An array or string of pluralised strings.
   */
  public function pluralise($string) {

    if (is_string($string)) {
      return Inflector::pluralize($string);
    }
    elseif (is_array($string)) {
      $strings = [];

      foreach ($string as $str) {
        $strings[] = Inflector::pluralize($str);
      }

      return $strings;
    }

    return FALSE;
  }

  /**
   * @seeAbove
   */
  public function pluralize($string) { $this->pluralise($string); }

  /**
   * Turns a string, or an array of strings, into singularised version of that string.
   *
   * Example usage:
   *  \Drupal::service('drupal.tweak)->singularise('canopy'); // Returns "canopies"
   *
   * @param $string
   *   Can be a single string, or an array of strings.
   *
   * @return array|bool|string
   *   An array or string of singularised strings.
   */
  public function singularise($string) {

    if (is_string($string)) {
      return Inflector::singularize($string);
    }
    elseif (is_array($string)) {
      $strings = [];

      foreach ($string as $str) {
        $strings[] = Inflector::singularize($str);
      }

      return $strings;
    }

    return FALSE;
  }

  /**
   * @seeAbove
   */
  public function singularize($string) { $this->singularise($string); }

  /**
   * Checks if a string contains another string.
   *
   * Example usage:
   *   \Drupal::service('drupal.tweak')->stringContains('Hello world', 'world');
   *   // returns TRUE.
   *
   * @param $string
   *   The string to search in.
   * @param $needle
   *   The string to search for.
   *
   * @return bool
   *   True if the string contains the needle, false is not.
   */
  public function stringContains($string, $needle) {
    return strpos($string, $needle) === FALSE ? FALSE : TRUE;
  }

  /**
   * Gets the public URL for a media entity.
   *
   * @param $media_entity
   *   The media entity.
   * @param $image_style
   *   The media image style. E.g. thumbnail, medium, large.
   *
   * @return string
   *   The URL string.
   */
  public function getImageUrlFromMediaEntity($media_entity, $image_style) {

    $media_field = '';
    $field_definitions = $media_entity->getFieldDefinitions();
    $media_field_types = ['image', 'file']; // @TODO Remaining media types?

    foreach ($field_definitions as $field_definition) {
      if ($field_definition instanceof FieldConfig) {
        if (in_array($field_definition->get('field_type'), $media_field_types)) {
          $media_field = $field_definition->getName();
        }
      }
    }

    if (empty($media_field)) {
      return FALSE;
    }

    return ImageStyle::load($image_style)->buildUrl($media_entity->field_media_image->entity->getFileUri());
  }

  /**
   * Gets the public URL for a media from a media ID.
   *
   * @param $media_entity
   *   The media entity.
   * @param $image_style
   *   The media image style. E.g. thumbnail, medium, large.
   *
   * @return string
   *   The URL string.
   */
  public function getImageUrlFromMediaId($id, $image_style = 'large') {
    $media_entity = Media::load($id);

    if (!empty($media_entity)) {
      return $this->getImageUrlFromMediaEntity($media_entity, $image_style);
    }

    return FALSE;
  }

  /**
   * Gets all entities of a specific type.
   *
   * Example usage:
   *   // Get all articles regardless of published status.
   *   $all_articles = \Drupal::service('drupal.tweak')->getAllEntitiesOfType('node', 'article');
   *   // Get all published articles only.
   *   $all_published_articles = \Drupal::service('drupal.tweak')->getAllEntitiesOfType('node', 'article', TRUE);
   *
   * @param $entity_type
   *
   * @param $bundle
   *   The entity bundle. E.g. "article"
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface[]|\Drupal\node\Entity\Node[]
   */
  public function getAllEntitiesOfType($entity_type, $bundle, $published = false) {

    $query = $this->entityQuery->get($entity_type)->condition('type', $bundle);

    if ($published) {
      $query->condition('status', '1');
    }

    $nids = $query->execute();

    if (empty($nids)) {
      return FALSE;
    }

    return Node::loadMultiple($nids);
  }

  /**
   * Gets the first entity of a certain type.
   *
   * Example usage:
   *   // Get first articles regardless of published status.
   *   $article = \Drupal::service('drupal.tweak')->getFirstEntityOfType('node', 'article');
   *   // Get first published articles only.
   *   $published_article = \Drupal::service('drupal.tweak')->getFirstEntityOfType('node', 'article', TRUE);
   *
   * @param $entity_type
   *   The type of entity. E.g. "node".
   * @param $bundle
   *   The entity bundle. E.g. "article"
   * @param $published
   *   Optionally set to true if the node must be published.
   *
   * @return mixed
   */
  public function getFirstEntityOfType($entity_type, $bundle, $published = false) {
    $nodes = $this->getAllEntitiesOfType($entity_type, $bundle, $published);
    return reset($nodes);
  }

  /**
   * Get the first node of a given type. Pass in last argument TRUE for published only nodes.
   *
   * Example usage:
   *   $article = \Drupal::service('drupal.tweak')->getFirstNodeOfType('article');
   *
   * @param $bundle
   *   The node type.
   *
   * @return \Drupal\node\Entity\Node
   *   The node object.
   */
  public function getFirstNodeOfType($bundle) {
    return $this->getFirstEntityOfType('node', $bundle, $published = false);
  }

  /**
   * Get the first taxonomy term of a given type. Pass in last argument TRUE for published only nodes.
   *
   * Example usage:
   *   $tag = \Drupal::service('drupal.tweak')->getFirstTermOfType('tags');
   *
   * @param $vocab
   *   The taxonomy term vocabulary.
   *
   * @return \Drupal\taxonomy\Entity\Term
   *   The term object.
   */
  public function getFirstTermOfType($vocab) {
    return $this->getFirstEntityOfType('taxonomy', $vocab, $published = false);
  }

  /**
   * @TODO
   *
   * @param $bundle
   *
   * @return mixed
   */
  public function getFirstMediaOfType($bundle) {
    return $this->getFirstEntityOfType('media', $bundle, $published = false);
  }

  /**
   * @TODO
   *
   * @param bool $entity_type
   *
   * @return bool|mixed|null
   */
  public function getCurrentEntity($entity_type = FALSE) {

    if (empty($entity_type)) {

      foreach ($this->entityTypes as $entity_type) {
        $entity = $this->currentRouteMatch->getParameter($entity_type);

        if (!empty($entity)) {
          return $entity;
        }
      }
    }

    if (!$entity_type) {
      return FALSE;
    }

    return \Drupal::routeMatch()->getParameter($entity_type);
  }

  /**
   * Gets the parent of a supplied taxonomy term. The argument can be a Term object or a term id.
   *
   * Example usage:
   *   $parent = \Drupal::service('drupal.tweak')->getTaxonomyTermParent('3');
   *   // OR use the term object.
   *   $term = \Drupal::service('drupal.tweak')->getCurrentEntity('term');
   *   $parent = \Drupal::service('drupal.tweak')->getTaxonomyTermParent($term);
   *
   * @param $term
   *   Either a Term object or a term id.
   *
   * @return bool|mixed
   *   The parent term.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTaxonomyTermParent($term) {

    if ($term instanceof Term) {
      $term = $term->id();
    }
    $parent_terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadParents($term);

    if (empty($parent_terms)) {
      return FALSE;
    }

    return reset($parent_terms);
  }

  /**
   * Gets the create form for an entity.
   *
   * Example usage:
   *   $article_form = \Drupal::service('drupal.tweak')->getEntityForm('node', 'article');
   *
   * @param $entity_type
   *   The entity type. Can be node, term or media.
   * @param $bundle
   *   The type or bundle. E.g. article.
   *
   * @return array
   *   The form as an array.
   */
  public function getEntityForm($entity_type, $bundle) {

    $entity = NULL;
    $entity_type = strtolower($entity_type);

    switch ($entity_type) {
      case 'node':
        $entity = Node::create(['type' => $bundle]);
        break;
      case 'term':
      case 'taxonomy':
      case 'taxonomy_term':
        $entity = Term::create(['type' => $bundle]);
        break;
      case 'media':
        $entity = Media::create(['type' => $bundle]);
        break;
      case 'user':
        $entity = User::create();
    }

    if (!empty($entity)) {
      return $this->entityFormBuilder->getForm($entity, 'default');
    }
  }

  /**
   * Gets the current fully aliased path with no parameters.
   *
   * @return string
   *   The current path.
   */
  public function getCurrentPath() {
    $current_path = $this->requestStack->getCurrentRequest()->getRequestUri();
    return strtok($current_path, '?');
  }

  /**
   * Redirect the user to a URL.
   *
   * @param $url
   *   The URL as a string or URL object.
   */
  public function goTo($url) {

    if (is_string($url)) {
      $response = new RedirectResponse($url);
    }
    else {
      $response = new RedirectResponse($url->toString());
    }

    isset($response) ? $response->send() : NULL;
  }

  /**
   * Returns a link as a standard Link render array.
   *
   * Example usage:
   *   $variables['link'] = \Drupal::service('drupal.tweak')->link('About us', '/about-us');
   *
   * @param $text
   *   The link text.
   * @param $url
   *   The URL as a string.
   *
   * @return bool|\Drupal\Core\Link
   */
  public function link(string $text, $url) {

    if (is_string($url)) {
      return Link::fromTextAndUrl(t('Admin System'), Url::fromUserInput($url));
    }
    elseif ($url instanceof Url) {
      return Link::fromTextAndUrl(t('Admin System'), $url);
    }

    return FALSE;
  }

  /**
   * Returns a link as formattable markup, so it won't be escaped when used in certain render arrays.
   *
   * Example usage:
   *   $variables['link'] = \Drupal::service('drupal.tweak')->linkSafe('About us', '/about-us');
   *
   * @param $text
   *   The link text.
   * @param $url
   *   The URL as a string.
   *
   * @return bool|\Drupal\Component\Render\FormattableMarkup
   */
  public function linkSafe($text, $url) {

    if (is_string($url)) {
      $link = Link::fromTextAndUrl($text, Url::fromUserInput($url))->toString()->getGeneratedLink();
      return new FormattableMarkup($link, []);
    }

    return FALSE;
  }

  /**
   * Checks if a user has at least one role in the provided array.
   *
   * Example usage:
   *   $is_editor = \Drupal::service('drupal.tweak')->userRoleOneOf($user, ['content_editor', 'site_editor']);
   *   // returns TRUE if the current user has either or both roles: 'content_editor', 'site_editor'.
   *
   * @param $user
   *   The user object.
   *
   * @param array $user_roles
   *   An array of user roles to check if the user has at least one of.
   *
   * @return bool
   *   TRUE if the user has at least one of the roles, FALSE if not.
   */
  public function userRoleOneOf($user, array $user_roles) {
    if (array_intersect($user->getRoles(), $user_roles)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Checks if the current user has at least one role in the provided array.
   *
   * Example usage:
   *   $is_editor = \Drupal::service('drupal.tweak')->currentUserRoleOneOf(['content_editor', 'site_editor']);
   *   // returns TRUE if the current user has either or both roles: 'content_editor', 'site_editor'.
   *
   * @param array $user_roles
   *   An array of user roles to check if the user has at least one of.
   *
   * @return bool
   *   TRUE if the user has at least one of the roles, FALSE if not.
   */
  public function userCurrentRoleOneOf(array $user_roles) {
    return $this->userRoleOneOf($this->currentUser, $user_roles);
  }

  /**
   * @TODO
   *
   * @return array
   */
  public function getUserRegisterForm() {
    return $this->getEntityForm('user', '');
  }

  /**
   * @TODO
   *
   * Consider using \Drupal::service('drupal.tweak')->getBlockPluginFromMachineName('user_login_block') to get the user login block.
   *
   * @return \Drupal\Component\Render\MarkupInterface|mixed
   */
  public function getUserLoginForm() {
    $form = \Drupal::formBuilder()->getForm(\Drupal\user\Form\UserLoginForm::class);
    return $this->renderer->renderRoot($form);
  }

  /**
   * Get ALL available user roles (apart from anon).
   *
   * @return array
   */
  public function getUserRolesAll() {
    return array_map(['\Drupal\Component\Utility\Html', 'escape'], user_role_names(TRUE));
  }

  /**
   * @return array
   */
  private function getMenuTreeManipulators() {
    return [
      ['callable' => 'menu.default_tree_manipulators:checkNodeAccess'],
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
  }

  /**
   * Combines multiple menus into one menu (array of menu items).
   *
   * Example usage:
   *   $variables['menu'] = \Drupal::service('drupal.tweak')->menuCombine(['main', 'footer']);
   *
   * @param array $menu_names
   *   An array of menu names.
   *
   * @return array
   *   The menu links array.
   */
  public function menuCombine(array $menu_names) {

    $combined_tree = [];
    $parameters = $this->menuLinkTree->getCurrentRouteMenuTreeParameters(trim($menu_names[0]));
    $manipulators = $this->getMenuTreeManipulators();
    $parameters->expandedParents = [];

    foreach ($menu_names as $menu_name) {
      $tree_items = $this->menuLinkTree->load(trim($menu_name), $parameters);
      $tree_manipulated = $this->menuLinkTree->transform($tree_items, $manipulators);
      $combined_tree = array_merge($combined_tree, $tree_manipulated);
    }

    return $this->menuLinkTree->build($combined_tree);
  }

  /**
   * Combines multiple menus into one menu and returns a render array.
   *
   * Example usage:
   *   $variables['menu'] = \Drupal::service('drupal.tweak')->menuCombine(['main', 'footer']);
   *
   * @param array $menu_names
   *   An array of menu names.
   *
   * @return \Drupal\Component\Render\MarkupInterface|mixed
   *   The menu as a renderable array.
   */
  public function menuCombineAndRender(array $menu_names) {
    $menu = $this->menuCombine($menu_names);
    return $this->renderer->renderRoot($menu);
  }

  /**
   * @TODO
   *
   * @param string $menu_name
   *
   * @return array
   */
  public function getMenuLinkSiblings(string $menu_name) {

    $parameters   = $this->menuLinkTree->getCurrentRouteMenuTreeParameters($menu_name);
    $active_trail = array_keys($parameters->activeTrail);

    $parent_link_id = isset($active_trail[1]) ? $active_trail[1] : $active_trail[0];

    $parameters->setRoot($parent_link_id);
    $parameters->setMaxDepth(1);
    $parameters->excludeRoot();
    $tree = $this->menuLinkTree->load($menu_name, $parameters);

    $manipulators = $this->getMenuTreeManipulators();
    $tree = $this->menuLinkTree->transform($tree, $manipulators);

    return $this->menuLinkTree->build($tree);
  }

  /**
   * Gets a block by the block ID. Usually a custom content block created in Drupal.
   *
   * For blocks created in other modules, or core blocks, create a new block using getBlockPluginFromMachineName();
   *
   * Example usage:
   *   \Drupal::service('drupal.tweak')->getBlockContentFromId(3);
   *
   * @param $block_id
   *   The block ID. Edit your custom block and note the number in the URL.
   *
   * @return array
   *   The block as a render array.
   */
  public function getBlockContentFromId($block_id) {
    $block = BlockContent::load($block_id);
    return $this->entityTypeManager->getViewBuilder('block_content')->view($block);
  }

  /**
   * @TODO
   *
   * @param $block_machine_name
   *
   * @return mixed
   */
  public function getBlockPluginFromMachineName($block_machine_name) {
    $plugin_block = $this->blockManager->createInstance($block_machine_name, []);
    return $plugin_block->build();
  }

  /**
   * Retrieves all of the values for a given key.
   *
   * @param array $array
   * @param $key
   *
   * @return array
   */
  public function arrayPluck(array $array, $key) {
    return array_map(function ($item) use ($key) {
      return is_object($item) ? $item->$key : $item[$key];
    }, $array);
  }

  /**
   * @TODO
   *
   * @param array $array
   *
   * @return array
   */
  public function arrayFlatten(array $array) {

    $result = [];
    foreach ($array as $item) {
      if (!is_array($item)) {
        $result[] = $item;
      } else {
        $result = array_merge($result, $this->arrayFlatten($item));
      }
    }

    return $result;
  }

  /**
   * @TODO
   *
   * @param $array
   * @param $key
   * @param string $sort
   *
   * @return array
   */
  public function arraySortByKey($array, $key, $sort = 'asc') {

    $sortedItems = [];
    foreach ($array as $item) {
      $key_ = is_object($item) ? $item->{$key} : $item[$key];
      $sortedItems[$key_] = $item;
    }
    if ($sort === 'desc') {
      krsort($sortedItems);
    }
    else {
      ksort($sortedItems);
    }

    return array_values($sortedItems);
  }

  /**
   * Calls a user defined function once. Handy to wrap inside of a hook.
   *
   * Example usage:
   *   hook_preprocess_region(...) { \Drupal::service('drupal.tweak')->callOnce( drupal_set_message('Will only display this message once.') }
   *
   * @param $function
   *
   * @return \Closure
   */
  public function callOnce($function) {

    return function (...$args) use ($function) {
      static $called = FALSE;
      if ($called) {
        return;
      }
      $called = TRUE;
      return $function(...$args);
    };
  }

  /**
   * Returns the file extension from a string.
   *
   * Example usage:
   *   \Drupal::service('drupal.tweak')->getExtension('hello.world.foo-bar.pdf');
   *   // returns "pdf".
   *
   * @param string $string
   *
   * @return string
   */
  public function getExtension(string $string) {
    return Path::getExtension($string);
  }

  /**
   * Converts a HTML string to plain text.
   *
   * Example usage:
   *   $html = 'Hello, &quot;<b>world</b>&quot;';
   *   $text = \Drupal::service('drupal.tweak')->htmlConvertToText($html);
   *   // returns 'Hello, "World"'
   *
   * @param string $html
   *
   * @return string
   */
  public function htmlConvertToText(string $html) {
    $html = new Html2Text($html);
    return $html->getText();
  }

  /**
   * Get the site name, slogan, and logo path (url).
   *
   * Example usage:
   *   $site_info = \Drupal::service('drupal.tweak')->getSiteInfo();
   *   // Returns (E.g.): ['My drupal site', 'Just another Drupal blog', '/themes/custom/my_theme/logo.png'];
   *
   * @return array
   *   An associative array of site info.
   */
  public function getSiteInfo() {

    $site_info = ['name', 'slogan'];
    foreach ($site_info as $key => $info_item) {
      $site_info[$key] = $this->configFactory->get('system.site')->get($info_item);
    }
    $site_info['logo_path'] = file_url_transform_relative(file_create_url(theme_get_setting('logo.url')));

    return $site_info;
  }

  /**
   * @return string
   */
  public function getActiveThemeName() {
    return $this->themeManager->getActiveTheme()->getName();
  }

  /**
   * @param string $parameter_type
   *
   * @return array|mixed
   */
  public function getCurrentUrlParameters(string $parameter_type = 'both') {
    $parameter_type = strtolower($parameter_type);

    switch ($parameter_type) {
      case 'get':
        return $this->requestStack->getCurrentRequest()->query->all();
        break;
      case 'post':
        return $this->requestStack->getCurrentRequest()->request->all();
        break;
      default:
        return [
          'get' => $this->requestStack->getCurrentRequest()->query->all(),
          'post' => $this->requestStack->getCurrentRequest()->request->all(),
        ];
        break;
    }
  }

  public function getViewAsExecuted($view_name, $view_display_name, array $arguments = []) {

    $view = Views::getView($view_name);

    if (is_object($view)) {
      $view->setArguments($arguments);
      $view->setDisplay($view_display_name);
      $view->preExecute();
      $view->execute();
      return $view;
    }

    return FALSE;
  }

  public function getViewAsRenderable($view_name, $view_display_name, array $arguments = []) {
    $view = $this->getViewAsExecuted($view_name, $view_display_name, $arguments);

    if (!empty($view)) {
      return $view->buildRenderable($view_display_name, $arguments);
    }

    return FALSE;
  }

  public function getAllFieldsOfType($field_type, $entity_type) {
    $fields = \Drupal::service('entity_field.manager')->getFieldMapByFieldType($field_type);

    if (empty($fields) || empty($fields[$entity_type])) {
      return FALSE;
    }

    return $fields[$entity_type];
  }
}
