<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2013 WeCenter. All Rights Reserved
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

class aws_weixin_enterprise_class extends AWS_MODEL
{
	public function get_access_token()
	{
		if (!AWS_APP::config()->get('weixin')->app_id)
		{
			return false;
		}
		
		$token_cache_key = 'weixin_access_token_' . md5(AWS_APP::config()->get('weixin')->app_id . AWS_APP::config()->get('weixin')->app_secret);
		
		if ($access_token = AWS_APP::cache()->get($token_cache_key))
		{
			return $access_token;
		}
		
		if ($result = curl_get_contents('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . AWS_APP::config()->get('weixin')->app_id . '&secret=' . AWS_APP::config()->get('weixin')->app_secret))
		{
			$result = json_decode($result, true);
			
			if ($result['access_token'])
			{
				AWS_APP::cache()->set($token_cache_key, $result['access_token'], $result['expires_in']);
				
				return $result['access_token'];
			}
		}
	}
	
	public function send_text_message($openid, $message)
	{
		if (!AWS_APP::config()->get('weixin')->app_id)
		{
			return false;
		}
		
		HTTP::request('https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=' . $this->get_access_token(), 'POST', preg_replace("#\\\u([0-9a-f]+)#ie", "convert_encoding(pack('H4', '\\1'), 'UCS-2', 'UTF-8')", json_encode(array(
			'touser' => $openid,
			'msgtype' => 'text',
			'text' => array(
				'content' => $message
			)
		))));
	}
	
	public function client_list_image_clean()
	{
		if (!is_dir(ROOT_PATH . 'weixin/list_image/'))
		{
			return false;
		}
		
		$mp_menu = get_setting('weixin_mp_menu');
		
		foreach ($mp_menu AS $key => $val)
		{
			if ($val['sub_button'])
			{
				foreach ($val['sub_button'] AS $sub_key => $sub_val)
				{
					$attach_list[] = $sub_val['attch_key'] . '.jpg';
				}
			}
			
			$attach_list[] = $val['attch_key'] . '.jpg';
		}
		
		$files_list = fetch_file_lists(ROOT_PATH . 'weixin/list_image/', 'jpg');
			    
	    foreach ($files_list AS $search_file)
	    {
	    	if (!in_array(str_replace('square_', '', base_name($search_file))))
	    	{
		    	unlink($search_file);
	    	}
		}
	}
	
	public function process_mp_menu_post_data($mp_menu_post_data)
	{
		if (!$mp_menu_post_data)
		{
			$mp_menu_post_data = array();
		}
		
		uasort($mp_menu_post_data, 'array_key_sort_asc_callback');
		
		foreach ($mp_menu_post_data AS $key => $val)
		{
			if ($val['sub_button'])
			{
				unset($mp_menu_post_data[$key]['key']);
				
				uasort($mp_menu_post_data[$key]['sub_button'], 'array_key_sort_asc_callback');
				
				foreach ($mp_menu_post_data[$key]['sub_button'] AS $sub_key => $sub_value)
				{
					if ($mp_menu_post_data[$key]['sub_button'][$sub_key]['name'] == '' OR $mp_menu_post_data[$key]['sub_button'][$sub_key]['key'] == '')
					{
						unset($mp_menu_post_data[$key]['sub_button'][$sub_key]);
						
						continue;
					}
					
					if (substr($mp_menu_post_data[$key]['sub_button'][$sub_key]['key'], 0, 7) == 'http://' OR substr($mp_menu_post_data[$key]['sub_button'][$sub_key]['key'], 0, 8) == 'https://')
					{
						$mp_menu_post_data[$key]['sub_button'][$sub_key]['type'] = 'view';
						$mp_menu_post_data[$key]['sub_button'][$sub_key]['url'] = $mp_menu_post_data[$key]['sub_button'][$sub_key]['key'];
					}
					else
					{
						$mp_menu_post_data[$key]['sub_button'][$sub_key]['type'] = 'click';
					}
				}
			}
			else
			{
				$mp_menu_post_data[$key]['type'] = 'click';
			}
			
			if ($mp_menu_post_data[$key]['name'] == '')
			{
				unset($mp_menu_post_data[$key]);
			}
			
			if (substr($mp_menu_post_data[$key]['key'], 0, 7) == 'http://' OR substr($mp_menu_post_data[$key]['key'], 0, 8) == 'https://')
			{
				$mp_menu_post_data[$key]['type'] = 'view';
				$mp_menu_post_data[$key]['url'] = $mp_menu_post_data[$key]['key'];
			}
		}
		
		return $mp_menu_post_data;
	}
	
	public function update_client_menu($mp_menu)
	{
		if (!AWS_APP::config()->get('weixin')->app_id)
		{
			return false;
		}
		
		foreach ($mp_menu AS $key => $val)
		{
			if ($val['sub_button'])
			{
				foreach ($val['sub_button'] AS $sub_key => $sub_val)
				{
					unset($sub_val['sort']);
					unset($sub_val['command_type']);
					unset($sub_val['attach_key']);
					
					if ($sub_val['type'] == 'view')
					{
						unset($sub_val['key']);
						
						if (strstr($sub_val['url'], get_setting('base_url')))
						{
							$sub_val['url'] = $this->model('openid_weixin')->redirect_url($sub_val['url']);
						}
					}
					
					$val['sub_button_no_key'][] = $sub_val;
				}
				
				$val['sub_button'] = $val['sub_button_no_key'];
				
				unset($val['sub_button_no_key']);
			}
			
			unset($val['sort']);
			unset($val['command_type']);
			unset($val['attach_key']);
			
			if ($val['type'] == 'view')
			{
				unset($val['key']);
				
				if (strstr($val['url'], get_setting('base_url')))
				{
					$val['url'] = $this->model('openid_weixin')->redirect_url($val['url']);
				}
			}
			
			$mp_menu_no_key[] = $val;
		}
		
		if ($result = HTTP::request('https://api.weixin.qq.com/cgi-bin/menu/create?access_token=' . $this->get_access_token(), 'POST', preg_replace("#\\\u([0-9a-f]+)#ie", "convert_encoding(pack('H4', '\\1'), 'UCS-2', 'UTF-8')", json_encode(array('button' => $mp_menu_no_key)))))
		{
			$result = json_decode($result, true);
			
			if ($result['errcode'])
			{
				return $result['errmsg'];
			}
		}
		else
		{
			return '由于网络问题, 菜单更新失败';
		}
	}
	
	public function get_client_list_image_by_command($command)
	{
		$mp_menu_data = get_setting('weixin_mp_menu');
		
		foreach ($mp_menu_data AS $key => $val)
		{
			if ($val['sub_button'])
			{
				foreach ($val['sub_button'] AS $sub_key => $sub_val)
				{
					if ($sub_key == $command)
					{
						return $sub_val['attch_key'];
					}
				}
			}
			
			if ($key == $command)
			{
				return $val['attch_key'];
			}
		}
	}
	
	public function register_user($access_token, $access_user)
	{
		if (!$access_token OR !$access_user['nickname'])
		{
			return false;
		}
		
		$access_user['nickname'] = str_replace(array(
			'?', '/', '&', '=', '#'
		), '', $access_user['nickname']);
		
		if ($this->model('account')->check_username($access_user['nickname']))
		{
			$access_user['nickname'] .= '_' . rand(1, 999);
		}
		
		if ($uid = $this->model('account')->user_register($access_user['nickname'], md5(rand(111111, 999999999))))
		{
			if ($access_user['headimgurl'])
			{
				if ($avatar_stream = curl_get_contents($access_user['headimgurl']))
				{
					$avatar_location = get_setting('upload_dir') . '/avatar/' . $this->model('account')->get_avatar($uid, '', 1) . $this->model('account')->get_avatar($uid, '', 2);
					
					$avatar_dir = str_replace(basename($avatar_location), '', $avatar_location);
					
					if ( ! is_dir($avatar_dir))
					{
						make_dir($avatar_dir);
					}
					
					if (@file_put_contents($avatar_location, $avatar_stream))
					{
						foreach(AWS_APP::config()->get('image')->avatar_thumbnail AS $key => $val)
						{			
							$thumb_file[$key] = $avatar_dir . $this->model('account')->get_avatar($uid, $key, 2);
							
							AWS_APP::image()->initialize(array(
								'quality' => 90,
								'source_image' => $avatar_location,
								'new_image' => $thumb_file[$key],
								'width' => $val['w'],
								'height' => $val['h']
							))->resize();	
						}
						
						$avatar_file = $this->model('account')->get_avatar($uid, null, 1) . basename($thumb_file['min']);
					}
				}
			}
			
			$this->model('account')->update_users_fields(array(
				'sex' => $access_user['sex'],
				'avatar_file' => $avatar_file
			), $uid);
			
			return $this->model('account')->get_user_info_by_uid($uid);
		}
	}
	
	public function get_qr_code($scene_id)
	{
		if (!AWS_APP::config()->get('weixin')->app_id)
		{
			return false;
		}
		
		if ($result = HTTP::request('https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=' . $this->get_access_token(), 'POST', preg_replace("#\\\u([0-9a-f]+)#ie", "convert_encoding(pack('H4', '\\1'), 'UCS-2', 'UTF-8')", json_encode(array(
			'expire_seconds' => 300,
			'action_name' => 'QR_SCENE',
			'action_info' => array(
				'scene' => array(
					'scene_id' => $scene_id
				)
			)
		)))))
		{
			$result = json_decode($result, true);
			
			if ($result['ticket'])
			{
				return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . $result['ticket'];
			}
		}
	}
}
