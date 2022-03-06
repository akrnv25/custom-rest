<?php
/*
Plugin Name: Custom REST
Plugin URI: https://github.com/korenev6052/custom-rest.git
Description: Custom routes for managing users
Version: 1.0.0
Author: Alex Korenev
Author URI: https://github.com/korenev6052
*/

add_action('rest_api_init', function() {
	$namespace = 'custom-rest';

	$read_current_user_route = 'users/me';
	$read_current_user_route_params = [
		'methods' => 'GET',
		'callback' => 'read_current_user',
		'permission_callback' => function($request) {
			return is_user_logged_in();
		}
	];
	register_rest_route($namespace, $read_current_user_route, $read_current_user_route_params);

	$read_user_route = 'users/(?P<id>\d+)';
	$read_user_route_params = [
		'methods' => 'GET',
		'callback' => 'read_user',
		'permission_callback' => function($request) {
			return is_user_logged_in();
		}
	];
	register_rest_route($namespace, $read_user_route, $read_user_route_params);

	$update_user_route = 'users/(?P<id>\d+)';
	$update_user_route_params = [
		'methods' => 'POST',
		'callback' => 'update_user',
		'permission_callback' => function($request) {
			return is_user_logged_in();
		}
	];
	register_rest_route($namespace, $update_user_route, $update_user_route_params);

	$reset_password_route = 'reset-password';
	$reset_password_route_params = [
		'methods' => 'POST',
		'callback' => 'reset_password_send_email'
	];
	register_rest_route($namespace, $reset_password_route, $reset_password_route_params);

	$create_user_route = 'users';
	$post_reset_password_params = [
		'methods' => 'POST',
		'callback' => 'create_new_user'
	];
	register_rest_route($namespace, $create_user_route, $post_reset_password_params);
});

function read_current_user(WP_REST_Request $request) {
	$user_id = get_current_user_id();
	return map_user($user_id);
}

function read_user(WP_REST_Request $request) {
	$user_id = $request['id'];
	return map_user($user_id);
}

function update_user(WP_REST_Request $request) {
	$user_id = $request['id'];
	$params = $request -> get_params();
	if (!is_null($params['email'])) {
		wp_update_user(['ID' => $user_id, 'user_email' => $params['email']]);
		update_user_meta($user_id, 'billing_email', $params['email']);
	}
	if (!is_null($params['password'])) {
		$userInstance = new WP_User(intval($user_id));
    	reset_password($userInstance, $params['password']);
	}
	if (!is_null($params['avatar'])) {
		update_user_meta($user_id, 'description', $params['avatar']);
	}
	if (!is_null($params['phone'])) {
		update_user_meta($user_id, 'billing_phone', $params['phone']);
	}
	return map_user($user_id);
}

function reset_password_send_email(WP_REST_Request $request) {
 	$email = $request -> get_param('email');
    $userdata = get_user_by('email', $email);
    if (empty($userdata)) {
        return 'User not found.';
    }
	$user = $userdata -> to_array();
	$password = wp_generate_password(8, true, true);
    $userInstance = new WP_User(intval($userdata -> ID));
    reset_password($userInstance, $password);
	$email_subject = '[Proleafy App] Password Changed';
	$headers = "From: '" . 'Proleafy App' . "' <" . 'proleafyapp@gmail.com' . "> \r\n";
	$headers .= "Reply-To: ". strip_tags($user['user_email']) . "\r\n";
	$headers .= "Content-Type:text/html;charset=utf-8";
	$email_message = '<html><body>';
	$email_message .= "<table>";
	$email_message .= "<tr><td>Username: </td><td>" . $user['user_login'] . "</td></tr>";
	$email_message .= "<tr><td>Email: </td><td>" . $user['user_email'] . "</td></tr>";
	$email_message .= "<tr><td>Password: </td><td>" . $password . "</td></tr>";
	$email_message .= "</table>";
	$email_message .= "</body></html>";
	$email_message = nl2br($email_message);
	wp_mail($user['user_email'], $email_subject, $email_message, $headers);
    return 'Password reset link has been sent to your registered email.';
}

function create_new_user(WP_REST_Request $request) {
	$params = $request -> get_params();
	$user_id = wp_insert_user([
		'user_login' => $params['login'],
		'user_email' => $params['email']
	]);
	if (is_wp_error($user_id)) {
		return $user_id -> get_error_message();
	}
	$userInstance = new WP_User(intval($user_id));
    reset_password($userInstance, $params['password']);
	update_user_meta($user_id, 'description', $params['avatar']);
	update_user_meta($user_id, 'billing_phone', $params['phone']);
	update_user_meta($user_id, 'billing_email', $params['email']);
	return map_user($user_id);
}

function map_user($user_id) {
	$user = get_userdata($user_id) -> to_array();
	$phone = get_user_meta($user_id, 'billing_phone', true);
	$avatar = get_user_meta($user_id, 'description', true);
	return [
		'phone' => $phone,
		'id' => $user['ID'],
		'login' => $user['user_login'],
		'email' => $user['user_email'],
		'avatar' => $avatar
	];
}

?>