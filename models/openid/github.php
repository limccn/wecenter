<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2014 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
*/

if (!defined('IN_ANWSION'))
{
    die;
}

class openid_github_class extends AWS_MODEL
{
    const OAUTH2_AUTH_URL = 'https://github.com/login/oauth/authorize';

    const OAUTH2_TOKEN_URL = 'https://github.com/login/oauth/access_token';

    const OAUTH2_DEBUG_TOKEN_URL = 'https://github.com/login/oauth/access_token';

    const OAUTH2_USER_INFO_URL = 'https://api.github.com/user';

    public $authorization_code;

    public $user_access_token;

    public $app_access_token;

    public $redirect_url;

    public $expires_time;

    public $error_msg;

    public $user_info;

    public $state;

    public function get_redirect_url($redirect_url, $state = null)
    {
        $args = array(
            'client_id' => get_setting('github_app_id'),
            'redirect_uri' => get_js_url($redirect_url),
            'response_type' => 'code',
            'scope' => 'user'
        );

        if ($state)
        {
            $args['state'] = $state;
            $this->state = $state;
        }

        return self::OAUTH2_AUTH_URL . '?' . http_build_query($args);
    }

    public function oauth2_login()
    {
        if (!$this->get_user_access_token() OR !$this->get_user_info())
        {
            if (!$this->error_msg)
            {
                $this->error_msg = AWS_APP::lang()->_t('Github 登录失败');
            }

            return false;
        }

        return true;
    }

    public function get_user_access_token()
    {
        if (!$this->authorization_code)
        {
            $this->error_msg = AWS_APP::lang()->_t('authorization code 为空');

            return false;
        }

        $args = array(
            'client_id' => get_setting('github_app_id'),
            'client_secret' => get_setting('github_app_secret'),
            'code' => $this->authorization_code,
            'redirect_uri' => get_js_url($this->redirect_url)
        );
       
          /* $result = curl_get_contents(self::OAUTH2_TOKEN_URL . '?' . http_build_query($args))*/
        
        $result = HTTP::request(self::OAUTH2_TOKEN_URL, 'POST', $args);

        if (!$result)
        {
            $this->error_msg = AWS_APP::lang()->_t('获取 user access token 时，与 Github 通信失败');

            return false;
        }

        parse_str($result, $user_access_token);

        if (!$user_access_token['access_token'])
        {
            $result = json_decode($result, true);

            $this->error_msg = ($result['error']) ? AWS_APP::lang()->_t('获取 user access token 失败，错误为：%s', $result['error']['message'])
                : AWS_APP::lang()->_t('获取 user access token 失败');

            return false;
        }

        $this->user_access_token = $user_access_token['access_token'];

        return true;
    }
  
    public function get_user_info()
    {
        if (!$this->user_access_token)
        {
            $this->error_msg = AWS_APP::lang()->_t('user access token 为空');

            return false;
        }

        $args = array(
            'access_token' => $this->user_access_token
        );

        $result = curl_get_contents(self::OAUTH2_USER_INFO_URL . '?' . http_build_query($args));

        if (!$result)
        {
            $this->error_msg = AWS_APP::lang()->_t('获取个人资料时，与 Github 通信失败');

            return false;
        }

        $result = json_decode($result, true);

        if ($result['error'])
        {
            $this->error_msg = AWS_APP::lang()->_t('获取个人资料失败，错误为：%s', $result['error']['message']);

            return false;
        }

        $this->user_info = array(
            'id' => $result['id'],
            'name' => $result['name'],
            'email' => $result['email'],
            'home_url' => $result['home_url'],
            'location' => $result['location'],
            'avatar_url' => $result['avatar_url'],
            'authorization_code' => $this->authorization_code,
            'access_token' => $this->user_access_token,
            'expires_time' => $this->expires_time
        );

        return true;
    }

    public function bind_account($github_user, $uid)
    {
        if ($this->get_github_user_by_id($github_user['id']) OR $this->get_github_user_by_uid($uid))
        {
            return false;
        }

        return $this->insert('users_github', array(
            'id' => htmlspecialchars($github_user['id']),
            'uid' => intval($uid),
            'name' => htmlspecialchars($github_user['name']),
            'email' => htmlspecialchars($github_user['email']),
            'home_url' => htmlspecialchars($github_user['home_url']),
            'location' => htmlspecialchars($github_user['location']),
            'avatar_url' => htmlspecialchars($github_user['avatar_url']),
            'access_token' => htmlspecialchars($github_user['access_token']),
            'expires_time' => intval($github_user['expires_time']),
            'add_time' => time()
        ));
    }

    public function update_user_info($id, $github_user)
    {
        if (!is_digits($id))
        {
            return false;
        }

        return $this->update('users_github', array(
            'name' => htmlspecialchars($github_user['name']),
            'email' => htmlspecialchars($github_user['email']),
            'home_url' => htmlspecialchars($github_user['home_url']),
            'location' => htmlspecialchars($github_user['location']),
            'avatar_url' => htmlspecialchars($github_user['avatar_url']),
            'access_token' => htmlspecialchars($github_user['access_token']),
            'expires_time' => intval($github_user['expires_time'])
        ), 'id = ' . $id);
    }

    public function unbind_account($uid)
    {
        if (!is_digits($uid))
        {
            return false;
        }

        return $this->delete('users_github', 'uid = ' . $uid);
    }

    public function get_github_user_by_id($id)
    {
        if (!is_digits($id))
        {
            return false;
        }

        static $github_user_info;

        if (!$github_user_info[$id])
        {
            $github_user_info[$id] = $this->fetch_row('users_github', 'id = ' . $id);
        }

        return $github_user_info[$id];
    }

    public function get_github_user_by_uid($uid)
    {
        if (!is_digits($uid))
        {
            return false;
        }

        static $github_user_info;

        if (!$github_user_info[$uid])
        {
            $github_user_info[$uid] = $this->fetch_row('users_github', 'uid = ' . $uid);
        }

        return $github_user_info[$uid];
    }
}
