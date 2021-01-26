<?php
declare(strict_types=1);

namespace PunktDe\Mautic\Mautic;

/***
 *
 * This file is part of the "Mautic" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Florian Wessels <f.wessels@Leuchtfeuer.com>, Leuchtfeuer Digital Marketing
 *
 ***/

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class SendFormService implements LoggerAwareInterface
{
  use LoggerAwareTrait;

  public function submitForm(string $url, array $data): int
  {
    $client = new Client();
    $multipart = [];

    $headers = $this->makeHeaders();
    $cookies = $this->makeCookies();
    $this->makeMultipart($multipart, 'mauticform', $data);

    if (\array_key_exists('mautic_device_id', $_COOKIE)) {
      $multipart[] = [
        'name' => 'mautic_device_id',
        'contents' => $_COOKIE['mautic_device_id'],
      ];
    }

    $result = null;
    try {
      $result = $client->post($url, [
        'cookies' => $cookies,
        'headers' => $headers,
        'multipart' => $multipart,
      ]);
    } catch (\Exception $e) {
      die(sprintf('%s: %s', $e->getCode(), $e->getMessage()));
//      $this->logger->critical();

      return 500;
    }

    $statusCode = $result->getStatusCode();

    return (int)$statusCode;
  }

  private function makeHeaders(): array
  {
    $headers = [];
    if (isset($_SERVER['HTTP_REFERER'])) {
      $headers['Referer'] = $_SERVER['HTTP_REFERER'];
    }
    $ip = $this->getIpFromServer();
    if ($ip !== '') {
      $headers['X-Forwarded-For'] = $ip;
      $headers['Client-Ip'] = $ip;
    }

    return $headers;
  }

  /**
   * Guesses IP address from $_SERVER
   */
  public function getIpFromServer(): string
  {
    $ip = '';
    $ipHolders = [
      'HTTP_CLIENT_IP',
      'HTTP_X_FORWARDED_FOR',
      'HTTP_X_FORWARDED',
      'HTTP_X_CLUSTER_CLIENT_IP',
      'HTTP_FORWARDED_FOR',
      'HTTP_FORWARDED',
      'REMOTE_ADDR',
    ];

    foreach ($ipHolders as $key) {
      if (!empty($_SERVER[$key])) {
        $ip = $_SERVER[$key];
        if (strpos($ip, ',') !== false) {
          // Multiple IPs are present so use the last IP which should be
          // the most reliable IP that last connected to the proxy
          $ips = explode(',', $ip);
          $ips = array_map('trim', $ips);
          $ip = end($ips);
        }
        $ip = trim($ip);
        break;
      }
    }

    return $ip;
  }

  private function makeCookies(): CookieJar
  {
    $cookies = new CookieJar(true);
    $this->addCookies($cookies, 'mtc_id');
    $this->addCookies($cookies, 'mtc_sid');
    $this->addCookies($cookies, 'mautic_device_id');
    $this->addCookies($cookies, 'mautic_session_id');

    return $cookies;
  }

  private function addCookies(CookieJar $cookies, string $cookieName)
  {
    if (\array_key_exists($cookieName, $_COOKIE)) {
      $cookies->setCookie(new SetCookie([
        'Name' => $cookieName,
        'Value' => $_COOKIE[$cookieName],
        'Domain' => $_SERVER['HTTP_HOST'],
      ]));
    }
  }

  private function makeMultipart(array &$multipart, string $path, array $data)
  {
    foreach ($data as $key => $value) {
      $tempPath = $path . '[' . $key . ']';
      if (is_array($value)) {
        $this->makeMultipart($multipart, $tempPath, $value);
      } else {
        $multipart[] = [
          'name' => $tempPath,
          'contents' => $value,
        ];
      }
    }
  }
}
