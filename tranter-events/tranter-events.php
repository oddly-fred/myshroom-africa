<?php
/**
 * Plugin Name: Tranter IT Events
 * Description: Lightweight event management and responsive frontend experience for Tranter IT.
 * Version: 1.0.0
 * Author: Tranter IT
 * Text Domain: tranter-events
 */
if (!defined('ABSPATH')) exit;

define('TRANTER_EVENTS_VERSION', '1.0.0');
define('TRANTER_EVENTS_FILE', __FILE__);
define('TRANTER_EVENTS_DIR', plugin_dir_path(__FILE__));
define('TRANTER_EVENTS_URL', plugin_dir_url(__FILE__));

final class Tranter_Events {
    const POST_TYPE = 'tranter_event';
    const TYPE_TAX = 'tranter_event_type';
    const PARTNER_TAX = 'tranter_partner';
    const NONCE = 'tranter_events_nonce';

    public function __construct() {
        add_action('init', [$this, 'register_content']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_event'], 10, 2);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
        add_shortcode('tranter_events', [$this, 'shortcode']);
        add_action('wp_ajax_tranter_filter_events', [$this, 'ajax_filter_events']);
        add_action('wp_ajax_nopriv_tranter_filter_events', [$this, 'ajax_filter_events']);
        add_action('admin_post_tranter_save_partner', [$this, 'save_partner']);
        add_action('admin_post_tranter_delete_partner', [$this, 'delete_partner']);
    }

    public static function activate() {
        $self = new self(); $self->register_content();
        if (!term_exists('Conference', self::TYPE_TAX)) wp_insert_term('Conference', self::TYPE_TAX);
        foreach (['Workshop','Summit','Webinar','Exhibition','Networking','Partner Event','Tranter Event','Other'] as $type) if (!term_exists($type, self::TYPE_TAX)) wp_insert_term($type, self::TYPE_TAX);
        if (!term_exists('ManageEngine', self::PARTNER_TAX)) wp_insert_term('ManageEngine', self::PARTNER_TAX);
        flush_rewrite_rules();
    }
    public static function deactivate() { flush_rewrite_rules(); }

    public function register_content() {
        register_post_type(self::POST_TYPE, [
            'labels' => ['name'=>'Events','singular_name'=>'Event','add_new'=>'Add New Event','add_new_item'=>'Add New Event','edit_item'=>'Edit Event','view_item'=>'View Event','search_items'=>'Search Events','not_found'=>'No events found'],
            'public'=>false, 'show_ui'=>true, 'show_in_menu'=>false, 'supports'=>['title','editor','thumbnail'], 'menu_icon'=>'dashicons-calendar-alt', 'capability_type'=>'post', 'map_meta_cap'=>true,
        ]);
        register_taxonomy(self::TYPE_TAX, self::POST_TYPE, ['labels'=>['name'=>'Event Types','singular_name'=>'Event Type'],'public'=>false,'show_ui'=>true,'show_admin_column'=>true,'hierarchical'=>false,'rewrite'=>false]);
        register_taxonomy(self::PARTNER_TAX, self::POST_TYPE, ['labels'=>['name'=>'Partners','singular_name'=>'Partner'],'public'=>false,'show_ui'=>true,'show_admin_column'=>true,'hierarchical'=>false,'rewrite'=>false]);
    }

    public function admin_menu() {
        add_menu_page('Tranter Events','Tranter Events','edit_posts','tranter-events',[$this,'events_page'],'dashicons-calendar-alt',25);
        add_submenu_page('tranter-events','All Events','All Events','edit_posts','tranter-events',[$this,'events_page']);
        add_submenu_page('tranter-events','Add New Event','Add New Event','edit_posts','post-new.php?post_type='.self::POST_TYPE);
        add_submenu_page('tranter-events','Partners','Partners','manage_categories','tranter-event-partners',[$this,'partners_page']);
        add_submenu_page('tranter-events','Event Types','Event Types','manage_categories','edit-tags.php?taxonomy='.self::TYPE_TAX.'&post_type='.self::POST_TYPE);
        add_submenu_page('tranter-events','Settings','Settings','manage_options','tranter-event-settings',[$this,'settings_page']);
    }
    public function events_page() { if (!current_user_can('edit_posts')) return; echo '<div class="wrap"><h1 class="wp-heading-inline">Tranter Events</h1> <a class="page-title-action" href="'.esc_url(admin_url('post-new.php?post_type='.self::POST_TYPE)).'">Add New Event</a>'; echo '<p>Manage upcoming and past events. Status is calculated automatically from the event date.</p>'; $screen = 'edit-'.self::POST_TYPE; echo '<a class="button" href="'.esc_url(admin_url('edit.php?post_type='.self::POST_TYPE)).'">Open Event List</a></div>'; }
    public function settings_page() { if (!current_user_can('manage_options')) return; if (isset($_POST['tranter_save_settings']) && check_admin_referer(self::NONCE)) update_option('tranter_events_settings', ['timezone'=>sanitize_text_field($_POST['timezone'] ?? 'Africa/Lagos')]); $s=get_option('tranter_events_settings',['timezone'=>'Africa/Lagos']); echo '<div class="wrap"><h1>Tranter Events Settings</h1><form method="post">'.wp_nonce_field(self::NONCE,'_wpnonce',true,false).'<table class="form-table"><tr><th><label for="timezone">Default timezone</label></th><td><input class="regular-text" id="timezone" name="timezone" value="'.esc_attr($s['timezone']).'"><p class="description">Used when an event timezone is not specified.</p></td></tr></table><p><button class="button button-primary" name="tranter_save_settings" value="1">Save Settings</button></p></form></div>'; }

    private function fields() { return ['start_date'=>'','end_date'=>'','start_time'=>'','end_time'=>'','timezone'=>'Africa/Lagos','location_name'=>'','location_city'=>'','location_country'=>'Nigeria','location_url'=>'','registration_enabled'=>'0','registration_url'=>'','registration_text'=>'Register Now','landing_enabled'=>'0','landing_url'=>'','gallery'=>'','videos'=>'']; }
    private function meta($id) { return wp_parse_args(array_map('maybe_unserialize', get_post_meta($id)), $this->fields()); }
    public function add_meta_boxes() { add_meta_box('tranter_event_details','Event Details',[$this,'details_box'],self::POST_TYPE,'normal','high'); add_meta_box('tranter_event_media','Gallery & Videos',[$this,'media_box'],self::POST_TYPE,'normal','default'); }
    public function details_box($post) { $m=$this->meta($post->ID); wp_nonce_field(self::NONCE,self::NONCE); $tax=wp_get_post_terms($post->ID,self::TYPE_TAX,['fields'=>'ids']); $partner=wp_get_post_terms($post->ID,self::PARTNER_TAX,['fields'=>'ids']); echo '<div class="tranter-admin-grid">';
        $this->input('Start date','start_date','date',$m['start_date']); $this->input('End date','end_date','date',$m['end_date']); $this->input('Start time','start_time','time',$m['start_time']); $this->input('End time','end_time','time',$m['end_time']); $this->input('Timezone','timezone','text',$m['timezone']); $this->input('Venue / Location','location_name','text',$m['location_name']); $this->input('City','location_city','text',$m['location_city']); $this->input('Country','location_country','text',$m['location_country']); $this->input('Maps / location URL','location_url','url',$m['location_url']);
        echo '<p><label><input type="checkbox" name="registration_enabled" value="1" '.checked($m['registration_enabled'],'1',false).'> Enable registration</label></p>'; $this->input('Registration URL','registration_url','url',$m['registration_url']); $this->input('Button text','registration_text','text',$m['registration_text']); echo '<p><label><input type="checkbox" name="landing_enabled" value="1" '.checked($m['landing_enabled'],'1',false).'> Use dedicated landing page</label></p>'; $this->input('Landing page URL','landing_url','url',$m['landing_url']); echo '</div><p class="description">Event status and year are generated automatically. Set an end date for multi-day events.</p>';
        echo '<hr><p><strong>Event Type</strong></p>'; wp_dropdown_categories(['taxonomy'=>self::TYPE_TAX,'name'=>'event_type','hide_empty'=>false,'show_option_none'=>'Select event type','selected'=>$tax[0]??0]); echo '<p><strong>Technology Partner</strong></p>'; wp_dropdown_categories(['taxonomy'=>self::PARTNER_TAX,'name'=>'event_partner','hide_empty'=>false,'show_option_none'=>'No partner','selected'=>$partner[0]??0]);
    }
    private function input($label,$name,$type,$value) { echo '<p><label><strong>'.esc_html($label).'</strong><br><input class="widefat" type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" /></label></p>'; }
    public function media_box($post) { $m=$this->meta($post->ID); echo '<p><label><strong>Gallery image IDs</strong><br><input class="widefat" name="gallery" value="'.esc_attr(is_array($m['gallery'])?implode(',',$m['gallery']):$m['gallery']).'"></label><span class="description">Comma-separated WordPress media IDs.</span></p><p><label><strong>Video URLs</strong><br><textarea class="widefat" rows="4" name="videos">'.esc_textarea(is_array($m['videos'])?implode("\n",$m['videos']):$m['videos']).'</textarea></label><span class="description">One YouTube or Vimeo URL per line.</span></p>'; }
    public function save_event($id,$post) { if (defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE) return; if (!isset($_POST[self::NONCE])||!wp_verify_nonce($_POST[self::NONCE],self::NONCE)||!current_user_can('edit_post',$id)) return; $text=['start_date','end_date','start_time','end_time','timezone','location_name','location_city','location_country','registration_text']; $url=['location_url','registration_url','landing_url']; foreach($text as $k) update_post_meta($id,$k,sanitize_text_field($_POST[$k]??'')); foreach($url as $k) update_post_meta($id,$k,esc_url_raw($_POST[$k]??'')); foreach(['registration_enabled','landing_enabled'] as $k) update_post_meta($id,$k,isset($_POST[$k])?'1':'0'); $gallery=array_filter(array_map('absint',explode(',',sanitize_text_field($_POST['gallery']??'')))); $videos=array_filter(array_map('esc_url_raw',preg_split('/\r\n|\r|\n/',$_POST['videos']??''))); update_post_meta($id,'gallery',$gallery); update_post_meta($id,'videos',$videos); wp_set_post_terms($id,[(int)($_POST['event_type']??0)],self::TYPE_TAX,false); wp_set_post_terms($id,[(int)($_POST['event_partner']??0)],self::PARTNER_TAX,false); }

    public function partners_page() { if (!current_user_can('manage_categories')) return; if (isset($_GET['deleted'])) echo '<div class="notice notice-success"><p>Partner deleted.</p></div>'; $terms=get_terms(['taxonomy'=>self::PARTNER_TAX,'hide_empty'=>false]); echo '<div class="wrap"><h1>Partners</h1><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tranter_save_partner">'.wp_nonce_field(self::NONCE,'_wpnonce',true,false).'<table class="form-table"><tr><th>Name</th><td><input name="partner_name" class="regular-text" required></td></tr><tr><th>Logo media ID</th><td><input name="partner_logo" type="number" class="regular-text"></td></tr><tr><th>Description</th><td><textarea name="partner_description" class="large-text"></textarea></td></tr><tr><th>Website</th><td><input name="partner_website" type="url" class="regular-text"></td></tr></table><p><button class="button button-primary">Add Partner</button></p></form><h2>Existing partners</h2><table class="widefat striped"><thead><tr><th>Name</th><th>Events</th><th>Action</th></tr></thead><tbody>'; foreach($terms as $t) echo '<tr><td>'.esc_html($t->name).'</td><td>'.esc_html($t->count).'</td><td><a href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=tranter_delete_partner&term_id='.$t->term_id),self::NONCE)).'">Delete</a></td></tr>'; echo '</tbody></table></div>'; }
    public function save_partner() { if (!current_user_can('manage_categories')||!check_admin_referer(self::NONCE)) wp_die('Unauthorized'); $name=sanitize_text_field($_POST['partner_name']??''); if ($name) { $r=wp_insert_term($name,self::PARTNER_TAX); if (!is_wp_error($r)) { $id=$r['term_id']; update_term_meta($id,'logo',absint($_POST['partner_logo']??0)); update_term_meta($id,'description',sanitize_textarea_field($_POST['partner_description']??'')); update_term_meta($id,'website',esc_url_raw($_POST['partner_website']??'')); }} wp_safe_redirect(admin_url('admin.php?page=tranter-event-partners')); exit; }
    public function delete_partner() { if (!current_user_can('manage_categories')||!check_admin_referer(self::NONCE)) wp_die('Unauthorized'); wp_delete_term(absint($_GET['term_id']??0),self::PARTNER_TAX); wp_safe_redirect(admin_url('admin.php?page=tranter-event-partners&deleted=1')); exit; }

    public function frontend_assets() { if (!is_singular() && !has_shortcode(get_post_field('post_content',get_queried_object_id()),'tranter_events')) return; wp_enqueue_style('tranter-events',TRANTER_EVENTS_URL.'assets/css/tranter-events.css',[],TRANTER_EVENTS_VERSION); wp_enqueue_script('tranter-events',TRANTER_EVENTS_URL.'assets/js/tranter-events.js',[],TRANTER_EVENTS_VERSION,true); wp_localize_script('tranter-events','TranterEvents', ['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce(self::NONCE)]); }
    public function admin_assets($hook) { if (strpos($hook,'tranter')!==false || get_current_screen() && get_current_screen()->post_type===self::POST_TYPE) wp_enqueue_style('tranter-events-admin',TRANTER_EVENTS_URL.'assets/css/tranter-events-admin.css',[],TRANTER_EVENTS_VERSION); }

    private function date_value($m,$end=false) { $date=$end&&$m['end_date']?$m['end_date']:($m['start_date']?:''); $time=$end&&$m['end_time']?$m['end_time']:($m['start_time']?:'00:00'); if (!$date) return 0; try { $tz=new DateTimeZone($m['timezone']?:'Africa/Lagos'); return (new DateTime($date.' '.$time,$tz))->getTimestamp(); } catch(Exception $e){ return 0; } }
    private function status($m) { return current_time('timestamp') <= $this->date_value($m,true) ? 'upcoming':'past'; }
    private function query_events($status='all',$limit=6,$type='',$partner='',$year='') { $meta_query=[]; $now=current_time('timestamp'); $ids=[]; $q=new WP_Query(['post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'meta_value','meta_key'=>'start_date','order'=>'ASC']); foreach($q->posts as $p){$m=$this->meta($p->ID);$s=$this->status($m);$y=(int)date('Y',$this->date_value($m)); if(($status==='upcoming'&&$s!=='upcoming')||($status==='past'&&$s!=='past')||($year&&$year!=$y)) continue; if($type&& !has_term($type,self::TYPE_TAX,$p))continue; if($partner&& !has_term($partner,self::PARTNER_TAX,$p))continue; $ids[]=$p->ID;} wp_reset_postdata(); if($status==='upcoming') usort($ids,function($a,$b){return get_post_time('U',true,$a)-get_post_time('U',true,$b);}); return array_slice($ids,0,$limit>0?$limit:6); }
    private function partner_data($id) { $t=wp_get_post_terms($id,self::PARTNER_TAX); if(!$t||is_wp_error($t))return null; $t=$t[0]; $logo=absint(get_term_meta($t->term_id,'logo',true)); return ['name'=>$t->name,'logo'=>$logo?wp_get_attachment_image_url($logo,'thumbnail'):'']; }
    private function event_data($id) { $p=get_post($id);$m=$this->meta($id);$type=wp_get_post_terms($id,self::TYPE_TAX,['fields'=>'names']);$pd=$this->partner_data($id);$start=$this->date_value($m);$end=$this->date_value($m,true);$gallery=is_array($m['gallery'])?$m['gallery']:[];$images=[];foreach($gallery as $gid){$u=wp_get_attachment_image_url($gid,'large');if($u)$images[]=$u;} return ['id'=>$id,'title'=>get_the_title($id),'description'=>apply_filters('the_content',$p->post_content),'short'=>get_post_meta($id,'short_description',true)?:wp_trim_words(wp_strip_all_tags($p->post_content),24),'image'=>get_the_post_thumbnail_url($id,'large'),'type'=>$type[0]??'Event','partner'=>$pd,'start'=>$start,'end'=>$end,'date'=>wp_date(get_option('date_format'),$start),'time'=>$m['start_time']?wp_date(get_option('time_format'),$start):'','location'=>implode(', ',array_filter([$m['location_name'],$m['location_city'],$m['location_country']])), 'location_url'=>$m['location_url'],'registration_enabled'=>$m['registration_enabled']==='1','registration_url'=>$m['registration_url'],'registration_text'=>$m['registration_text']?:'Register Now','landing_enabled'=>$m['landing_enabled']==='1','landing_url'=>$m['landing_url'],'gallery'=>$images,'videos'=>is_array($m['videos'])?$m['videos']:[],'status'=>$this->status($m)]; }
    public function shortcode($atts) { $a=shortcode_atts(['limit'=>6,'show_filters'=>'true','show_gallery'=>'true','show_videos'=>'true'],$atts,'tranter_events'); $up=$this->query_events('upcoming',(int)$a['limit']);$past=$this->query_events('past',(int)$a['limit']);$next=$up? $this->event_data($up[0]):null; $years=get_terms(['taxonomy'=>self::TYPE_TAX,'hide_empty'=>true]); ob_start(); include TRANTER_EVENTS_DIR.'templates/events.php'; return ob_get_clean(); }
    public function ajax_filter_events() { check_ajax_referer(self::NONCE,'nonce'); $status=sanitize_key($_POST['status']??'all');$type=sanitize_text_field($_POST['event_type']??'');$partner=sanitize_text_field($_POST['partner']??'');$year=absint($_POST['year']??0);$ids=$this->query_events($status,100,$type,$partner,$year); $data=[];foreach($ids as $id)$data[]=$this->event_data($id); wp_send_json_success(['events'=>$data]); }
}
register_activation_hook(__FILE__,['Tranter_Events','activate']); register_deactivation_hook(__FILE__,['Tranter_Events','deactivate']); new Tranter_Events();
function tranter_events_render_card($e){ include TRANTER_EVENTS_DIR.'templates/event-card.php'; }
function tranter_events_render_modal($e){ include TRANTER_EVENTS_DIR.'templates/event-modal.php'; }
