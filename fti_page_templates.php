<?php
/*
	Plugin Name: Page Template Usage Info
	Description: Provides usage information of custom page templates in the current theme
	Version: 1.2
    Text Domain: pagetemplateusageinfo
	Author: Shawn Hooper
	Author URI: http://www.shawnhooper.ca/

	Copyright 2014-2016 Fivesense Technologies Inc. (support@fivesense.ca)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

/* Include Plugin Framework */
require_once(ABSPATH . '/wp-admin/includes/plugin.php');

/* Call Hooks */
add_action('admin_menu', 'fti_page_templates_register_menu');
add_action('admin_init', 'fti_page_templates_register_stylesheet');


function fti_page_templates_register_menu() {
    add_submenu_page('edit.php?post_type=page', _x('Page Template Usage', 'Admin Menu Name', 'pagetemplateusageinfo'), _x('Page Template Usage', 'Admin Menu Name', 'pagetemplateusageinfo'), 'administrator', 'fti_page_templates', 'fti_page_templates' );
}

function fti_page_templates_register_stylesheet() {
    // Respects SSL, Style.css is relative to the current file
    wp_register_style( 'fti_page_templates_stylesheet', plugins_url('fti_page_templates.css', __FILE__) );
    wp_enqueue_style( 'fti_page_templates_stylesheet' );
}

function fti_page_templates_get_templateUsage($template_filename = '') {
    /** @var $wpdb WPDB */
    global $wpdb;

    if ($template_filename != '') {
        $sql = $wpdb->prepare("SELECT p.ID, u.display_name AS post_author, post_date, post_title, post_status, post_modified FROM " . $wpdb->prefix . "posts p INNER JOIN " . $wpdb->prefix . "users u ON p.post_author = u.ID  LEFT JOIN " . $wpdb->prefix . "postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_page_template' WHERE p.post_type = 'page' AND p.post_status IN ('publish', 'draft')  AND pm.meta_value = '%s'", $template_filename);
    } else {
        $sql = "SELECT IFNULL(pm.meta_value, 'page.php') AS template_filename, COUNT(*) AS pages_using FROM " . $wpdb->prefix . "posts p LEFT JOIN " . $wpdb->prefix . "postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_page_template' WHERE p.post_type = 'page' AND p.post_status IN ('publish', 'draft') GROUP BY IFNULL(pm.meta_value, 'page.php')";
    }

    return $wpdb->get_results($sql, OBJECT_K);
}

function fti_page_templates() {

    if (isset($_GET['template'])) {
        fti_page_templates_single($_GET['template']);
    } else {
        fti_page_templates_summary();
    }
}

function fti_page_templates_summary() {
    echo '<h1>' . _x('Page Template Usage', 'Admin Menu Name', 'pagetemplateusageinfo') . '</h1>';

    // Get the List of Templates from the theme
    $templates = get_page_templates();
    // Get the list of templates references in the database
    $templateUse = fti_page_templates_get_templateUsage();

    // Sort array by key
    ksort($templates);

    // Print theme count info
    echo sprintf('<p>' . _x('There are <strong>%d</strong> templates included in the <strong>%s</strong> theme.', 'Template Statistics', 'pagetemplateusageinfo') . '</p>', count($templates), wp_get_theme());

    echo '<table id="fti_page_templates_templateList"><thead><tr><th>' . _x('Tempalte Name', 'Table Column Heading', 'pagetemplateusageinfo') . '</th><th>' . _x('Tempalte Filename', 'Table Column Heading', 'pagetemplateusageinfo') . '</th><th>' . _x('# of Pages Using Template', 'Table Column Heading', 'pagetemplateusageinfo') . '</th></tr></thead><tbody>';

    // Start with Default Template
    echo '<tr>';
    echo sprintf('<td>%s</td><td>%s</td><td><a href="edit.php?post_type=page&page=fti_page_templates&template=%s">%d</a></td>', 'page.php', 'default', 'page.php', $templateUse['default']->pages_using);
    echo '</tr>';

    // Loop through the themes
    foreach ($templates as $template_name => $template_filename) {
        echo '<tr>';
        echo sprintf('<td>%s</td><td>%s</td><td><a href="edit.php?post_type=page&page=fti_page_templates&template=%s">%d</a></td>', $template_name, $template_filename, $template_filename, $templateUse[$template_filename]->pages_using);
        echo '</tr>';
    }

    echo '</tbody></table>';

    // Sort the Table by Page Usage
    echo '<script>jQuery(document).ready(function() { jQuery("#fti_page_templates_templateList").tablesorter({sortList: [[2,0]]}) })</script>';
}

function fti_page_templates_single($template_filename) {
    echo '<h1>' . _x('Page Template Usage', 'Admin Menu Name', 'pagetemplateusageinfo') . ': ' . $template_filename .'</h1>';

    // Get the pages that use this template
    if ($template_filename == 'page.php') $template_filename = 'default';
    $pages = fti_page_templates_get_templateUsage($template_filename);

    // Print Page Info
    echo sprintf('<p>' . _x('There are <strong>%d</strong> page(s) on this site that make use of the <strong>%s</strong> template.', 'Template Statistics', 'pagetemplateusageinfo') . '</p>', count($pages), $template_filename);

    echo '<form id="fti_page_templates_form" method="post" action="edit.php?post_type=page&page=fti_page_templates"><input type="submit" value="' . esc_attr_x('Return to Template List', 'Navigation Link Text', 'pagetemplateusageinfo') . '" /></form>';


    echo '<table id="fti_page_templates_pageList"><thead><tr><th>' . _x('ID', 'Table Column Header', 'pagetemplateusageinfo') . '</th><th>' . _x('Title', 'Table Column Header. Refers to a page title.', 'pagetemplateusageinfo') . '</th><th>' . _x('Status', 'Table Column Header. Refers to post status (published, draft, etc.)', 'pagetemplateusageinfo') . '</th><th>' . _x('Author', 'Table Column Header. Refers to the name of a page\'s author', 'pagetemplateusageinfo') . '</th><th>' . _x('Created', 'Table Column Header. The date a page was created.', 'pagetemplateusageinfo') . '</th><th>' . _x('Modified', 'Table Column Header. The date this page was last modified', 'pagetemplateusageinfo') . '</th><th>' . _x('Actions', 'The heading of a table column where the cells contains hyperlinks to interact with the data in that row (edit, delete, view, etc.)', 'pagetemplateusageinfo') . '</th></tr></thead><tbody>';

    // Loop through the pages
    foreach ($pages as $page) {
        echo '<tr>';
        echo sprintf('<td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">' . _x('View', 'Link to view the selected page in your browser', 'pagetemplateusageinfo') . '</a> | <a href="%s">' . _x('Edit', 'Link - Edit the selected page', 'pagetemplateusageinfo') . '</a></td>', $page->ID, $page->post_title, $page->post_status, $page->post_author, $page->post_date, $page->post_modified, get_permalink($page->ID), get_edit_post_link($page->ID));
        echo '</tr>';
    }

    echo '</tbody></table>';


}