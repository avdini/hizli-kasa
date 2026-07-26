<?php
/**
 * Hızlı Kasa - System Logs REST API Controller (V2)
 *
 * Log verilerinin filtrelenmiş listelenmesi, trace akışlarının çekilmesi
 * ve log temizleme işlemlerini V2 API standartlarında yönetir.
 *
 * @package HizliKasa
 */

if (!defined('ABSPATH')) {
    exit;
}

class Hizli_Kasa_API_Logs extends Hizli_Kasa_API_Controller_Base {

    /**
     * REST API rotalarını kaydeder.
     */
    public function register_routes() {
        register_rest_route($this->namespace, '/logs', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_logs_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'page'      => ['required' => false, 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
                'limit'     => ['required' => false, 'type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint'],
                'channel'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
                'level'     => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
                'search'    => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'user_id'   => ['required' => false, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                'date_from' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'date_to'   => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route($this->namespace, '/logs/trace/(?P<request_id>[a-zA-Z0-9_]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_trace_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/logs/stats', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_stats_callback'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route($this->namespace, '/logs/clear', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'clear_logs_callback'],
            'permission_callback' => [$this, 'check_permission'],
            'args'                => [
                'days' => ['required' => false, 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }

    /**
     * Yetki kontrolü.
     */
    public function check_permission($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('Bu işlem için yönetici yetkiniz bulunmuyor.', 'hizli-kasa'),
                ['status' => 403]
            );
        }
        return true;
    }

    public function get_logs_callback($request) {
        return $this->handle_request([$this, 'get_logs'], $request);
    }

    public function get_trace_callback($request) {
        return $this->handle_request([$this, 'get_trace'], $request);
    }

    public function get_stats_callback($request) {
        return $this->handle_request([$this, 'get_stats'], $request);
    }

    public function clear_logs_callback($request) {
        return $this->handle_request([$this, 'clear_logs'], $request);
    }

    /**
     * Filtrelenmiş ve sayfalanmış log verilerini döner.
     */
    protected function get_logs($request) {
        global $wpdb;

        $table_name = Hizli_Kasa_Database::get_tables()['logs'];

        $page      = max(1, (int) $request->get_param('page'));
        $limit     = min(100, max(1, (int) $request->get_param('limit')));
        $offset    = ($page - 1) * $limit;

        $channel   = $request->get_param('channel');
        $level     = $request->get_param('level');
        $search    = $request->get_param('search');
        $user_id   = $request->get_param('user_id');
        $date_from = $request->get_param('date_from');
        $date_to   = $request->get_param('date_to');

        $where   = ['1=1'];
        $params  = [];

        if (!empty($channel)) {
            $where[]  = 'channel = %s';
            $params[] = $channel;
        }

        if (!empty($level)) {
            $where[]  = 'level = %s';
            $params[] = $level;
        }

        if (!empty($user_id)) {
            $where[]  = 'user_id = %d';
            $params[] = $user_id;
        }

        if (!empty($date_from)) {
            $where[]  = 'created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }

        if (!empty($date_to)) {
            $where[]  = 'created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        if (!empty($search)) {
            $where[]  = '(message LIKE %s OR request_id LIKE %s OR context LIKE %s)';
            $like_val = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like_val;
            $params[] = $like_val;
            $params[] = $like_val;
        }

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";
        if (!empty($params)) {
            $total_items = (int) $wpdb->get_var($wpdb->prepare($count_sql, $params));
        } else {
            $total_items = (int) $wpdb->get_var($count_sql);
        }

        $query_params = array_merge($params, [$limit, $offset]);
        $select_sql   = "SELECT * FROM {$table_name} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
        $results      = $wpdb->get_results($wpdb->prepare($select_sql, $query_params));

        $formatted_items = [];
        foreach ($results as $row) {
            $formatted_items[] = [
                'id'          => (int) $row->id,
                'request_id'  => $row->request_id,
                'channel'     => $row->channel,
                'level'       => $row->level,
                'message'     => $row->message,
                'user_id'     => (int) $row->user_id,
                'object_type' => $row->object_type,
                'object_id'   => (int) $row->object_id,
                'context'     => $row->context ? json_decode($row->context, true) : null,
                'created_at'  => $row->created_at,
            ];
        }

        $total_pages = ceil($total_items / $limit);

        return Hizli_Kasa_API_Response::success([
            'items'       => $formatted_items,
            'pagination'  => [
                'total_items' => $total_items,
                'total_pages' => (int) $total_pages,
                'current_page'=> $page,
                'limit'       => $limit,
            ],
        ]);
    }

    /**
     * Belirli bir request_id değerine ait zincirleme log geçmişini döner.
     */
    protected function get_trace($request) {
        global $wpdb;

        $request_id = sanitize_key($request->get_param('request_id'));
        $table_name = Hizli_Kasa_Database::get_tables()['logs'];

        $sql     = $wpdb->prepare("SELECT * FROM {$table_name} WHERE request_id = %s ORDER BY id ASC", $request_id);
        $results = $wpdb->get_results($sql);

        $trace_items = [];
        foreach ($results as $row) {
            $trace_items[] = [
                'id'          => (int) $row->id,
                'request_id'  => $row->request_id,
                'channel'     => $row->channel,
                'level'       => $row->level,
                'message'     => $row->message,
                'user_id'     => (int) $row->user_id,
                'object_type' => $row->object_type,
                'object_id'   => (int) $row->object_id,
                'context'     => $row->context ? json_decode($row->context, true) : null,
                'created_at'  => $row->created_at,
            ];
        }

        return Hizli_Kasa_API_Response::success([
            'request_id'  => $request_id,
            'total_steps' => count($trace_items),
            'steps'       => $trace_items,
        ]);
    }

    /**
     * Log istatistiklerini döner.
     */
    protected function get_stats($request) {
        global $wpdb;

        $table_name = Hizli_Kasa_Database::get_tables()['logs'];
        $today      = current_time('Y-m-d') . ' 00:00:00';

        $total_logs   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $today_errors = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE level = 'error' AND created_at >= %s", $today));

        $channel_counts = $wpdb->get_results("SELECT channel, COUNT(*) as count FROM {$table_name} GROUP BY channel", OBJECT_K);
        $level_counts   = $wpdb->get_results("SELECT level, COUNT(*) as count FROM {$table_name} GROUP BY level", OBJECT_K);

        return Hizli_Kasa_API_Response::success([
            'total_logs'    => $total_logs,
            'today_errors'  => $today_errors,
            'channels'      => array_map(function($item) { return (int) $item->count; }, $channel_counts),
            'levels'        => array_map(function($item) { return (int) $item->count; }, $level_counts),
        ]);
    }

    /**
     * Logları temizler.
     */
    protected function clear_logs($request) {
        global $wpdb;

        $days = (int) $request->get_param('days');
        $deleted = Hizli_Kasa_Logger::purge_old_logs($days > 0 ? $days : 0, 0);

        if ($days === 0) {
            $table_name = Hizli_Kasa_Database::get_tables()['logs'];
            $wpdb->query("TRUNCATE TABLE {$table_name}");
            $deleted = "Tüm loglar temizlendi.";
        }

        return Hizli_Kasa_API_Response::success([
            'message' => 'Log temizleme işlemi tamamlandı.',
            'details' => $deleted,
        ]);
    }
}
