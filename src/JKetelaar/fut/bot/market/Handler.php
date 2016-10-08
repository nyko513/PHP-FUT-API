<?php
/**
 * @author JKetelaar
 */

namespace JKetelaar\fut\bot\market;

use Curl\Curl;
use JKetelaar\fut\bot\config\Configuration;
use JKetelaar\fut\bot\config\URL;
use JKetelaar\fut\bot\errors\market\IncorrectEndpoint;
use JKetelaar\fut\bot\errors\market\IncorrectHeaders;
use JKetelaar\fut\bot\errors\market\MarketError;
use JKetelaar\fut\bot\errors\market\UnknownEndpoint;
use JKetelaar\fut\bot\errors\market\UnparsableEndpoint;
use JKetelaar\fut\bot\market\handler\Method;
use JKetelaar\fut\bot\user\User;

class Handler {

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var User
     */
    private $user;

    /**
     * Handler constructor.
     *
     * @param Curl $curl
     * @param User $user
     */
    public function __construct(Curl $curl, User $user) {
        $this->curl = $curl;
        $this->user = $user;
    }

    public function getCredits() {
        $result = $this->sendRequest(URL::API_CREDITS);
        if(isset($result[ 'credits' ])) {
            return $result[ 'credits' ];
        }

        return null;
    }

    /**
     * @param string $url
     * @param Method $method
     * @param array  $data
     * @param null   $headers
     *
     * @return array|bool|null|string
     * @throws IncorrectEndpoint
     * @throws IncorrectHeaders
     * @throws MarketError
     * @throws UnknownEndpoint
     * @throws UnparsableEndpoint
     */
    public function sendRequest($url, Method $method = Method::GET, $data = [], $headers = null) {
        $curl = &$this->curl;

        foreach($this->user->getHeaders() as $key => $header) {
            $curl->setHeader($key, $header);
        }

        if(filter_var($url, FILTER_VALIDATE_URL) !== false) {
            throw new IncorrectEndpoint($url);
        } else {
            $url = $this->user->getHeaders()[ Configuration::X_UT_ROUTE_PARAM ] . $url;
            if(filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new UnparsableEndpoint($url);
            }
        }

        if($headers != null && is_array($headers)) {
            if(array_keys($headers) !== range(0, count($headers) - 1)) {
                throw new IncorrectHeaders();
            }

            foreach($headers as $key => $header) {
                $curl->setHeader($key, $header);
            }
        }

        $curl->setHeader('X-HTTP-Method-Override', $method);
        $curl->post($url, $data);

        if($curl->error) {
            throw new MarketError(null, $curl->errorCode, $curl->errorMessage);
        }

        if($curl->httpStatusCode == 404) {
            throw new UnknownEndpoint($url);
        }

        return json_decode(json_encode($curl->response), true);
    }

    public function getCurrencies() {
        $result = $this->sendRequest(URL::API_CREDITS);
        if(isset($result[ Currency::TAG ])) {
            $currencies = [];

            foreach($result[ Currency::TAG ] as $currency) {
                $currencies[] = new Currency(
                    $currency[ 'name' ], $currency[ 'funds' ], $currency[ 'finalFunds' ]
                );
            }

            return $currencies;
        }

        return null;
    }
}