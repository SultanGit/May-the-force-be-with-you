<?php

use Model\Boosterpack_model;
use Model\Post_model;
use Model\User_model;
use Model\Login_model;
use Model\Comment_model;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 10.11.2018
 * Time: 21:36
 */
class Main_page extends MY_Controller
{

    public function __construct()
    {

        parent::__construct();

        if (is_prod())
        {
            die('In production it will be hard to debug! Run as development environment!');
        }
    }

    public function index()
    {
        $user = User_model::get_user();

        App::get_ci()->load->view('main_page', ['user' => User_model::preparation($user, 'default')]);
    }

    public function get_all_posts()
    {
        $posts =  Post_model::preparation_many(Post_model::get_all(), 'default');
        return $this->response_success(['posts' => $posts]);
    }

    public function get_boosterpacks()
    {
        $posts =  Boosterpack_model::preparation_many(Boosterpack_model::get_all(), 'default');
        return $this->response_success(['boosterpacks' => $posts]);
    }

    public function login()
    {
        // TODO: task 1, аутентификация
        App::get_ci()->load->library('form_validation');
        App::get_ci()->form_validation->set_rules('login', 'Login', 'required');
        App::get_ci()->form_validation->set_rules('password', 'Password', 'required');

        if ( ! App::get_ci()->form_validation->run()) {
            return $this->response_error('request_failed_validation', [],400);
        }

        $email = App::get_ci()->input->post_get('login');
        $password = App::get_ci()->input->post_get('password');

        try {
            $user = Login_model::login($email, $password);

            if ( ! $user->is_loaded()) {
                return $this->response_error('unauthorized', [],401);
            }
        } catch (Exception $exception) {
            return $this->response_error('internal_server_error', [],500);
        }

        return $this->response_success(['user' => User_model::preparation($user, 'default')]);
    }

    public function logout()
    {
        // TODO: task 1, аутентификация

        Login_model::logout();

        $user = User_model::get_user();

        $this->response_success(['user' => User_model::preparation($user, 'default')]);
    }

    public function comment()
    {
        // TODO: task 2, комментирование

        App::get_ci()->load->library('form_validation');
        App::get_ci()->form_validation->set_rules('postId', 'PostId', 'required');
        App::get_ci()->form_validation->set_rules('commentText', 'CommentText', 'required');

        if ( ! App::get_ci()->form_validation->run()) {
            return $this->response_error('request_failed_validation', [],400);
        }

        $user = User_model::get_user();

        if ( ! User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH, [], 401);
        }

        $postId = App::get_ci()->input->post_get('postId');
        $text = App::get_ci()->input->post_get('commentText');

        $createStatus = Comment_model::create([
            'user_id'   => $user->get_id(),
            'assign_id' => $postId,
            'text'      => $text,
            'likes'     => 0,
        ]);

        if ( ! $createStatus) {
            return $this->response_error('internal_server_error', [],500);
        }

        $this->response_success([], 201);
    }

    public function like_comment(int $comment_id)
    {
        // TODO: task 3, лайк поста

        if ( ! User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH, [], 401);
        }

        $user = User_model::get_user();

        if ( $user->get_likes_balance() < 1 ) {
            return $this->response_error('not_enough_likes', [], 400);
        }

        $comment = new Comment_model($comment_id);

        if ( ! $comment->increment_likes($user) ) {
            return $this->response_error('internal_server_error', [],500);
        };

        return $this->response_success(['likes' => $comment->get_likes()]);
    }

    public function like_post(int $post_id)
    {
        // TODO: task 3, лайк поста

        if ( ! User_model::is_logged()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH, [], 401);
        }

        $user = User_model::get_user();

        if ( $user->get_likes_balance() < 1 ) {
            return $this->response_error('not_enough_likes', [], 400);
        }

        $post = new Post_model($post_id);

        if ( ! $post->increment_likes($user) ) {
            return $this->response_error('internal_server_error', [],500);
        };

        return $this->response_success(['likes' => $post->get_likes()]);
    }

    public function add_money()
    {
        // TODO: task 4, пополнение баланса

        App::get_ci()->load->library('form_validation');
        App::get_ci()->form_validation->set_rules('sum', 'Sum', 'required');
        if ( ! App::get_ci()->form_validation->run()) {
            return $this->response_error('request_failed_validation', [],400);
        }

        $sum = App::get_ci()->input->post('sum');

        /*
         * проверки на коректность введенной введенной суммы
         */
        if ( ! is_numeric($sum) ) {
            return $this->response_error('request_failed_validation', [],400);
        } elseif (stristr($sum, '.') !== false) {
            if (strlen(explode('.', $sum)[1]) > 2) {
                return $this->response_error('request_failed_validation', [],400);
            }
        }

        App::get_ci()->load->driver('cache', array('adapter' => 'apc'));
        App::get_ci()->load->helper('cookie');

        $userIdempotencyKey = App::get_ci()->input->cookie('idempotency_key', true);

        /*
         * проверка на повторно отправленный запрос (двойной клик отправки в форме поплнение и т.д.)
         */
        if ( ! empty($userIdempotencyKey)) {
            return $this->response_error('the_request_has_already_been_sent', [],400);
        }
        App::get_ci()->input->set_cookie('idempotency_key', uniqid(), 10);

        $user = User_model::get_user();

        $sum = (float)$sum;

        if ( ! $user->add_money($sum) ) {
            return $this->response_error('internal_server_error', [],500);
        }

        return $this->response_success();

    }

    public function get_post(int $post_id) {
        // TODO получения поста по id
        $post = new Post_model($post_id);
        return $this->response_success(['post' => Post_model::preparation($post, 'full_info')]);
    }

    public function buy_boosterpack()
    {
        // Check user is authorize
        if ( ! User_model::is_logged())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        // TODO: task 5, покупка и открытие бустерпака
    }





    /**
     * @return object|string|void
     */
    public function get_boosterpack_info(int $bootserpack_info)
    {
        // Check user is authorize
        if ( ! User_model::is_logged())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }


        //TODO получить содержимое бустерпака
    }
}
