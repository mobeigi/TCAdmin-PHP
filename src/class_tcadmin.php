<?php
/**
* TCAdmin Class
*
* Allows you to perform various actions via TCAdmin
*
*/

require_once(__DIR__ . '/vendor/autoload.php');

class TCAdmin
{
  private $base_url; //must use http protocol
  private $service_home_url;
  private $username;
  private $password;
  private $browser_like_headers;
  
  //Anti-forgery tokens
  private $event_validation;
  private $viewstate;
  private $viewstate_generator;
  private $viewstate_encrypted;
  
  private $guzzle_client;
  private $is_authed = false;
  
  function __construct($base_url, $username, $password)
  {
    $this->base_url = $base_url;
    $this->auth_url = $this->base_url . '/Interface/Base/Login.aspx';
    $this->service_home_url = $this->base_url . '/Interface/GameHosting/ServiceHome.aspx';
    $this->username = $username;
    $this->password = $password;
   
    $this->browser_like_headers = [
      'Accept' => '*/*',
      'Accept-Language' => 'en-GB,en-US;q=0.8,en;q=0.6,bg;q=0.4,de;q=0.2,fi;q=0.2',
      'Origin' => $this->base_url,
      'Referer' => $this->service_home_url,
      'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/47.0.2526.73 Safari/537.36',
      'X-Requested-With' => 'XMLHttpRequest',
    ];
  }
  
  public function login()
  {
    //Used a shared client cookie jar
    $this->guzzle_client = new \GuzzleHttp\Client(['cookies' => true]);
    
    $r = $this->guzzle_client->request('GET', $this->auth_url, [
      'headers' => $this->browser_like_headers,
    ]);
    
    if (!$this->get_anti_forgery_tokens($r->getBody()))
      return false;
    
    $login_payload = [
      'ctl00$ContentPlaceHolderMain$Login1$TextBoxUserId' => $this->username,
      'ctl00$ContentPlaceHolderMain$Login1$TextBoxPassword' => $this->password,
      'ctl00$ContentPlaceHolderMain$Login1$DropDownListLanguages' => 'en-AU',
      'ctl00$ContentPlaceHolderMain$Login1$DropDownListTheme' => '1:7dbc12ef-0ca8-4020-b0db-3d49f6386218', //TODO: Change to default
      '__EVENTTARGET' => 'ctl00$ContentPlaceHolderMain$Login1$ButtonLogin',
      '__EVENTVALIDATION' => $this->event_validation,
      '__VIEWSTATE' => $this->viewstate,
      '__VIEWSTATEGENERATOR' => $this->viewstate_generator,
      '__VIEWSTATEENCRYPTED' => $this->viewstate_encrypted,
    ];
    
    $r = $this->guzzle_client->request('POST', $this->auth_url, [
      'headers' => $this->browser_like_headers,
      'form_params' => $login_payload,
    ]);
    
    if (!$this->get_anti_forgery_tokens($r->getBody()))
      return false;
    
    $this->is_authed = true;
    
    return true;
  }
  
  public function logout()
  {
    unset($this->guzzle_client);
    $this->is_authed = false;
  }
  
  public function perform_fastdownloadsync($service_id)
  {
    if (!$this->is_authed) {
      trigger_error('Must be authed to use this function.');
      return false;
    }
    
    //Get service home page URL
    $service_page_url = $this->service_home_url . '?serviceid=' . $service_id;
    
    $r = $this->guzzle_client->request('GET', $service_page_url, [
      'headers' => $this->browser_like_headers,
    ]);
    
    if (!$this->get_anti_forgery_tokens($r->getBody()))
      return false;
    
    //Send POST to fetch fast download link
    $headers = $this->browser_like_headers;
    $headers['Referer'] = $service_page_url; //change referer
    $headers['X-MicrosoftAjax'] = 'Delta=true'; //add required field
    
    $payload = [
      'ctl00$DefaultScriptManager' => 'ctl00$ctl00$PageIcons1$RadToolBarPageIconsPanel|ctl00$PageIcons1$RadToolBarPageIcons',
      'RadAJAXControlID' => 'ctl00_DefaultAjaxManager',
      '__EVENTTARGET' => 'ctl00$PageIcons1$RadToolBarPageIcons',
      '__EVENTARGUMENT' => '2', // Changes based on how many tiles exit on service page panel.
      '__EVENTVALIDATION' => $this->event_validation,
      '__VIEWSTATE' => $this->viewstate,
      '__VIEWSTATEGENERATOR' => $this->viewstate_generator,
      '__VIEWSTATEENCRYPTED' => $this->viewstate_encrypted,
    ];
    
    $r = $this->guzzle_client->request('POST', $service_page_url, [
      'headers' => $headers,
      'form_params' => $payload,
    ]);
    
    //Fetch single use FastDL Link
    if (preg_match("/TCAdmin.Utility.openWindow\('(http:\/\/.*\/Monitor\/Public\/GameHosting\/FastDownloadSync.aspx\?key=.*?)', 'ConsoleWindow', 'Synchronize files on/", $r->getBody(), $fast_download_link))
      $fast_download_link = $fast_download_link[1];
    else
      return false;
    
    //Run Fast DL sync using one time link
    $r = $this->guzzle_client->request('GET', $fast_download_link);
    
    return true;
  }
  
  public function perform_steamupdate($service_id)
  {
    if (!$this->is_authed) {
      trigger_error('Must be authed to use this function.');
      return false;
    }
    
    //Get service home page URL
    $service_page_url = $this->service_home_url . '?serviceid=' . $service_id;
    
    $r = $this->guzzle_client->request('GET', $service_page_url, [
      'headers' => $this->browser_like_headers,
    ]);
    
    if (!$this->get_anti_forgery_tokens($r->getBody()))
      return false;
    
    //Send POST to fetch fast download link
    $headers = $this->browser_like_headers;
    $headers['Referer'] = $service_page_url; //change referer
    $headers['X-MicrosoftAjax'] = 'Delta=true'; //add required field
    
    $payload = [
      'ctl00$DefaultScriptManager' => 'ctl00$ctl00$PageIcons1$RadToolBarPageIconsPanel|ctl00$PageIcons1$RadToolBarPageIcons',
      'RadAJAXControlID' => 'ctl00_DefaultAjaxManager',
      '__EVENTTARGET' => 'ctl00$PageIcons1$RadToolBarPageIcons',
      '__EVENTARGUMENT' => '0', // Changes based on how many tiles exit on service page panel.
      '__EVENTVALIDATION' => $this->event_validation,
      '__VIEWSTATE' => $this->viewstate,
      '__VIEWSTATEGENERATOR' => $this->viewstate_generator,
      '__VIEWSTATEENCRYPTED' => $this->viewstate_encrypted,
    ];
    
    $r = $this->guzzle_client->request('POST', $service_page_url, [
      'headers' => $headers,
      'form_params' => $payload,
    ]);
    
    //Fetch single use Steam Update Link
    if (preg_match("/TCAdmin.Utility.openWindow\('(http:\/\/.*\/Monitor\/Public\/GameHosting\/SteamUpdate.aspx\?key=.*?)', 'ConsoleWindow', 'Run the Steam update on/", $r->getBody(), $steam_update_link))
      $steam_update_link = $steam_update_link[1];
    else
      return false;
    
    //Run Steam update using one time link
    $r = $this->guzzle_client->request('GET', $steam_update_link);
    
    return true;
  }
  
  /** 
  * Get anti forgery tokens
  * @param string $html html output from a http response
  * @return true if both tokens fetched, false otherwise
  */
  private function get_anti_forgery_tokens($html)
  {
    //Fetch event validation and viewstate tokens
    if (preg_match('/<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="(.*?)" \/>/', $html, $event_validation))
      $this->event_validation = $event_validation[1];
    else
      return false;
    
    if (preg_match('/<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="(.*?)" \/>/', $html, $viewstate))
      $this->viewstate = $viewstate[1];
    else
      return false;
    
    if (preg_match('/<input type="hidden" name="__VIEWSTATEGENERATOR" id="__VIEWSTATEGENERATOR" value="(.*?)" \/>/', $html, $viewstate_generator))
      $this->viewstate_generator = $viewstate_generator[1];
    else
      return false;
    
    if (preg_match('/<input type="hidden" name="__VIEWSTATEENCRYPTED" id="__VIEWSTATEENCRYPTED" value="(.*?)" \/>/', $html, $viewstate_encrypted))
      $this->viewstate_encrypted = $viewstate_encrypted[1];
    else
      return false;
    
    //Success
    return true;
  }
}

?>