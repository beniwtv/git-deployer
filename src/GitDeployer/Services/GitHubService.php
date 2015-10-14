<?php
namespace GitDeployer\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\Console\Question\Question;

class GitHubService extends BaseService {
    
    /**
     * Holds the URL to the GitHub instance
     * (may be changed for enterprise users)
     * @var string
     */
    protected $url = 'https://api.github.com/';

    /**
     * Holds the private token of this
     * user, used to authenticate to the API
     * @var string
     */
    protected $token;

    //////////////////////////////////////////////////////////////////
    // Service functions
    //////////////////////////////////////////////////////////////////

     /**
     * Asks a few questions, and uses the answers
     * to log the user in - we also save some info
     * as default for next time
     * @param  \Symfony\Component\Console\Helper\HelperSet $helpers
     * @return boolean
     */
    public function login() {

        if (strlen($this->token) > 0) {
            $client = $this->_createClient($this->token);

            try {
                $response = $client->get('user');
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                // Login no longer valid?                
                if ($e->getResponse()->getStatusCode() == 401
                    || $e->getResponse()->getStatusCode() == 403) {
                    return $this->_interactiveLogin();
                } else {
                    throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());            
                }
            }
        } else {
            return $this->_interactiveLogin();
        }

    }

    /**
     * Get a list of projects from GitHub
     * @return array of \Git-Deployer\Objects\Project
     */
    public function getProjects($url = 'user/repos') {

        if (strlen($this->token) > 0) {
            $client = $this->_createClient($this->token);

            try {
                $response = $client->get($url);
                $projects = json_decode($response->getBody());

                $projects = array_map( function ($p) {
                    $project = new \GitDeployer\Objects\Project();
                    $project->name($p->name)
                            ->description($p->description)
                            ->url($p->clone_url)
                            ->homepage($p->html_url);

                    return $project;
                }, $projects);

                if ($response->hasHeader('link')) {
                    // -> We have more than one page, extract the next
                    // page link and pass it to getProjects() again
                    $link = $response->getHeader('link')[0];

                    preg_match('/<.*user\/repos(.*)>; rel="next"/', $link, $matches);

                    if (isset($matches[1])) {
                        $cleanLink = 'user/repos' . $matches[1];

                        $moreProjects = $this->getProjects($cleanLink);
                        if (is_array($moreProjects)) $projects = array_merge($projects, $moreProjects);
                    }
                }

                return $projects;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()->getStatusCode() == 401
                    || $e->getResponse()->getStatusCode() == 403) {
                    return $this->_interactiveLogin();
                } else {
                    throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());            
                }
            }
        } else {
            throw new \Exception('You must log-in to a service first!');            
        }

    }

    //////////////////////////////////////////////////////////////////
    // Helpers
    //////////////////////////////////////////////////////////////////

    /**
     * Creates a new Guzzle HTTP client
     * @param  $token The private access token to use, if any
     * @return \GuzzleHttp\Client
     */
    private function _createClient($token = null) {

        $config = [
            // Base URI is used with relative requests
            'base_uri'          => $this->url,
            'allow_redirects'   => true,
            'headers'           => [
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ];

        if ($token != null) {
            $config['headers']['Authorization'] = 'token ' . $token;
        }

        $client = new Client($config);
        return $client;

    }

    /**
     * Logs into GitLab with user/password
     * @return boolean
     */
    private function _interactiveLogin() {

        // -> Display some help on how to create a private access key
        $this->output->writeln(<<<HELP
<info>You MUST create a personal access token for Git-Deployer before you
are able to log-in to GitHub. To do this, follow the instructions on 
the following page:
</info>
<comment>https://help.github.com/articles/creating-an-access-token-for-command-line-use/</comment>

<info>You will need to allow these scopes: <comment>repo, public_repo, repo:status, user</comment></info>

HELP
        );

        $helper = $this->helpers->get('question');
        
        // -> Get GitHub instance URL
        $defText = (strlen($this->url) > 3 ? '[' . $this->url . '] ' : '' );
        $question = new Question('Please enter the URL to the GitHub API (enterprise users need to change this): ' . $defText, $this->url);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 4) {
                throw new \RuntimeException(
                    'The URL for the GitHub PI can not be empty!'
                );
            }

            return $answer;
        });

        $this->url = $helper->ask($this->input, $this->output, $question);

        // -> Get GitHub login access token
        $defText = (strlen($this->token) > 3 ? '[' . $this->token . '] ' : '' );
        $question = new Question('Please enter your GitHub personal access token: ' . $defText, $this->token);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 4) {
                throw new \RuntimeException(
                    'The token can not be empty!'
                );
            }

            return $answer;
        });

        $this->token = $helper->ask($this->input, $this->output, $question);    

        // -> Now that we have al the necessary information, log us
        // into the GitHub API
        $this->output->writeln('<info>Logging in to GitHub...</info>');
    
        $client = $this->_createClient();

        // Login is OAuth
        try {
            $response = $client->get('user', [
                'headers' => [
                    'Authorization' => 'token ' . $this->token
                ]
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {            
            if ($e->getResponse()->hasHeader('X-GitHub-OTP')) {
                //-> Needs OTP auth, ask for it
                $question = new Question('Please enter your 2-factor authentication code displayed on your authenticator: ');
                $question->setValidator(function ($answer) {
                    if (strlen($answer) < 4) {
                        throw new \RuntimeException(
                            'The authentication code can not be empty!'
                        );
                    }

                    return $answer;
                });

                $otp = $helper->ask($this->input, $this->output, $question);           

                try {
                    $response = $client->get('user', [
                        'headers'   => [
                            'Authorization' => 'token ' . $this->token,
                            'X-GitHub-OTP'  => $otp
                        ]
                    ]);
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());           
                }
            } else {
                throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());           
            }
        }

        if ($response->getStatusCode() == 200) {
            $json = json_decode($response->getBody());
            $this->output->writeln('<comment>Hello ' . $json->name . '!</comment>');
                
            return true;
        } else {
            throw new \Exception($response->getStatusCode() . ' ' . $response->getReasonPhrase());
        }
    }

    /**
     * Makes sure we only serialize needed data, else we may
     * put too much cruft in the serialized file that we can't restore
     * @return array
     */
    public function __sleep() {

        return array('url', 'token');
        
    }

}