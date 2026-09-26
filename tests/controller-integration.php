<?php
/** Disposable WordPress with AS Local CSS active. */
wp_set_current_user(1);
function aslc_test($ok,$label){if(!$ok){throw new RuntimeException($label);}echo "PASS: $label\n";}
$requests=0;$guard=function()use(&$requests){$requests++;return new WP_Error('unexpected_http','Metadata must not fetch releases');};add_filter('pre_http_request',$guard,10,3);
for($i=0;$i<10;$i++){get_site_transient('update_plugins');$links=apply_filters('plugin_row_meta',[],'as-local-css/as-local-css.php',[],'all');}
aslc_test($requests===0,'metadata reads make zero HTTP calls');
aslc_test(count(array_filter($links,static fn($l)=>strpos($l,'>GitHub<')!==false))===1,'exactly one repository link');
aslc_test(count(array_filter($links,static fn($l)=>strpos($l,'>Check for updates<')!==false))===1,'controller owns check link');
aslc_test(!class_exists('ASLC_GitHub_Updater',false),'independent updater is not loaded');
remove_filter('pre_http_request',$guard,10);
$fixture=get_option('aslc_migration_fixture');
if($fixture){
 ob_start();CustomCSSandJS()->{'print_frontend-css-header-external'}();$output=ob_get_clean();
 aslc_test($output===get_option('aslc_previous_output'),'fresh-request CSS output is unchanged');
 aslc_test(get_post($fixture['post'])->post_content===$fixture['css'],'authored snippet retained');
 aslc_test(aslc_read_generated_css('controller-migration.css')===$fixture['css'],'editor reads unchanged authored CSS');
 aslc_enqueue_editor_css_file('frontend-css-header-external','controller-migration.css');
 aslc_test(wp_style_is('aslc-editor-css-'.md5('frontend-css-header-external|controller-migration.css'),'enqueued'),'block-editor stylesheet remains enqueued');
}
