<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use QL\QueryList;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Firefox\FirefoxOptions;

class PxController extends Controller{
    public function index(Request $request){
        $url        = 'https://qnh.meituan.com/api/account/login?service=shuguopai-admin&bgSource=3&continue=https%3A%2F%2Fqnh.meituan.com%2Fapi%2Fv1%2Feplogin%2Fcallback%3FcallbackUrl%3Dhttps%253A%252F%252Fqnh.meituan.com%252Fepassport-callback.html%26appId%3D3%26bizAppId%3D2%26appName%3D%25E7%2589%25B5%25E7%2589%259B%25E8%258A%25B1';
        $account    = [
            'login'     => '111111',
            'password'  => '111111',
            'static'    => 'false',

        ];
        dd(QueryList::get('https://qnh.meituan.com/login.html'));


        // dd(WebDriverBy::id('login'));

        $serverUrl = 'http://localhost:4444';
        $desiredCapabilities = DesiredCapabilities::firefox();

        // Disable accepting SSL certificates
        $desiredCapabilities->setCapability('acceptSslCerts', false);

        // Add arguments via FirefoxOptions to start headless firefox
        $firefoxOptions = new FirefoxOptions();
        // $firefoxOptions->addArguments(['-headless']);
        $firefoxOptions->addArguments(['--connect-existing']);
        $desiredCapabilities->setCapability(FirefoxOptions::CAPABILITY, $firefoxOptions);

        $driver = RemoteWebDriver::create($serverUrl, $desiredCapabilities);
        // $driver->get('https://qnh.meituan.com/login.html');
        // $driver->switch_to_frame(WebDriverBy::cssSelector('qnh-login-sdk'));
        // $driver->get('https://qnh-epassport.meituan.com/account/unitivelogin?service=shuguopai-admin&bgSource=3&continue=https%3A%2F%2Fqnh.meituan.com%2Fapi%2Fv1%2Feplogin%2Fcallback%3FcallbackUrl%3Dhttps%253A%252F%252Fqnh.meituan.com%252Fepassport-callback.html%26appId%3D3%26bizAppId%3D2%26appName%3D%25E7%2589%25B5%25E7%2589%259B%25E8%258A%25B1');
        $driver->get('http://www.px.com/cc');
        dd($driver);
        $driver->findElement(WebDriverBy::id('login'))->sendKeys('admin');//->submit();
        $driver->findElement(WebDriverBy::id('password'))->sendKeys('111111')->submit();
    }

    public function cc(Request $request){
        dd($request->all(), $_SERVER);
    }
}
