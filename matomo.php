<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;


class MatomoPlugin extends Plugin
{

    public $configs;
    const HTML_TAG_REGEX = '(<[^>]*?script.*?>([^<]*)</[^>]*?script.*?>)';

    protected $url;
    protected $id;
    protected $token;

    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0]
        ];
    }

    public function onPluginsInitialized ()
    {
        $this->getConfigs();
        if ($this->isSetup())
        {
            if ($this->isAdmin())
            {
                $this->enable([
                    'onAdminMenu' => ['onAdminMenu', 0],
                    'onAssetsInitialized' => ['onAssetsInitialized', 0]
                ]);
            }

            $this->enable([
                'onAssetsInitialized' => ['onAssetsInitialized', 0],
                'onTwigSiteVariables' => ['onTwigVariables', 0]
            ]);
        }
    }

    private function isSetup()
    {
        if (empty($this->configs['trackingId']) || empty($this->configs['hosted_url']) || empty($this->configs['token_auth']))
        {
            return false;
        }

        $this->id = $this->configs['trackingId'];
        $this->url = str_replace(array('http://','https://'),'', $this->configs['hosted_url']);
        $this->token = $this->configs['token_auth'];

        return true;
    }

    public function onAssetsInitialized()
    {
        if ($this->isAdmin())
        {
            $this->grav['assets']->addCss('user/plugins/matomo/css/admin.css', 1);
            return;
        }

        if (isset($this->configs['custom_code']))
        {
            preg_match(self::HTML_TAG_REGEX, $this->configs['custom_code'], $matches);
            $this->grav['assets']->addInlineJs($matches[1] ?? $this->configs['custom_code']);
        } else {
            $this->grav['assets']->addInlineJs($this->getTrackingCode());
        }

        if ($this->configs['popup']['enabled'])
        {
            $this->grav['assets']->addCss('plugin://matomo/css/matomo.css');
            $this->grav['assets']->addJs('plugin://matomo/js/jquery.ihavecookies.js');
            $this->grav['assets']->addJs('plugin://matomo/js/script.js');

            $popup = $this->configs['popup'];
            unset($popup['enabled']);
            $this->grav['assets']->addInlineJs("window.matomo = window.matomo || {};window.matomo.popup = ".json_encode($popup));
        }
    }

    public function onTwigVariables()
    {
        if (!$this->isAdmin())
        {
            if ($this->configs['gdpr']['enabled'])
            {
                $gdpr = $this->configs['gdpr'];
                $bc = $gdpr['bc'] ?? '';
                $fs = $gdpr['fs'] ?? '';
                $fc = $gdpr['fc'] ?? '';
                $ff = $gdpr['ff'] ?? '';

                $this->grav['twig']->twig_vars['matomo']['gdpr'] = <<<HTML
                <iframe
                style="border: 0; width: 100%;margin: -8px;"
                src="//$this->url/index.php?module=CoreAdminHome&action=optOut&language={$this->grav['language']->getActive()}&backgroundColor={$bc}&fontColor={$fc}&fontSize={$fs}&fontFamily={$ff}"></iframe>
HTML;
            }
        }
    }

    public function onTwigTemplatePaths()
    {
        if ($this->isAdmin())
        {
            $this->grav['twig']->twig_paths[] = __DIR__ . '/admin/templates';
        }
//        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onAdminMenu()
    {
        $this->grav['twig']->plugins_hooked_nav['Stats'] = ['route' => 'stats', 'icon' => ' fa-bar-chart-o'];
    }

    public function getConfigs()
    {
        $this->configs = $this->config->get('plugins.matomo');
    }

    private function getTrackingCode()
    {
        $code  = $this->addCodeOptions();
        $code .= "_paq.push(['trackPageView']);";
        $code .= "_paq.push(['enableLinkTracking']);";
        $code .= "(function() {";
        $code .= "var u='//{$this->url}';";
        $code .= "_paq.push(['setTrackerUrl', u+'js/']);";
        $code .= "_paq.push(['setSiteId', '{$this->id}']);";
        $code .= "var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];";
        $code .= "g.type='text/javascript'; g.async=true; g.defer=true; g.src=u+'js/'; s.parentNode.insertBefore(g,s);";
        $code .= "})();";
        return $code;
    }

    private function addCodeOptions()
    {
        $host = $this->grav['uri']->host();
        $rp = $this->getRootPath();
        $custom_vars = $this->configs['track_custom_vars'];
        $popup = $this->configs['popup'];

        $options = [
            'track_subdomains'         => '_paq.push(["setCookieDomain", "*.'.$host.'"]);',
            'prepend_domain'           => '_paq.push(["setDocumentTitle", document.domain + "/" + document.title]);',
            'hide_alias'               => '_paq.push(["setDomains", ["*.'.$host.''.$rp.'"]]);',
            'do_not_track'             => '_paq.push(["setDoNotTrack", true]);',
            'disable_tracking_cookies' => '_paq.push(["disableCookies"]);',
        ];

        $string = "var _paq = _paq || [];";

        foreach ($this->configs as $key => $value){
            if($value){
                $string .= isset($options[$key]) ? $options[$key] : '';
            }
        }

        if ($popup['enabled'])
        {
            $string .= "_paq.push(['requireConsent']);";
        }

        if($custom_vars){
            $i = 1;
            foreach ($custom_vars as $key => $value) {
                $string .= '_paq.push(["setCustomVariable", '.$i.', "'.$key.'", "'.$value.'", "visit"]);';
                $i++;
                if ($i == 6) break;
            }
        }

        return $string;
    }

    // I dont remember why this is here/needed
    private function getRootPath()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $root_path = str_replace(' ', '%20', rtrim(substr($_SERVER['PHP_SELF'], 0, strpos($_SERVER['PHP_SELF'], 'index.php')), '/'));
        // check if userdir in the path and workaround PHP bug with PHP_SELF
        if (strpos($uri, '/~') !== false && strpos($_SERVER['PHP_SELF'], '/~') === false) {
            $root_path = substr($uri, 0, strpos($uri, '/', 1)) . $root_path;
        }
        return $root_path;
    }

}
