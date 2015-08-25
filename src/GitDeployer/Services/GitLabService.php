<?php
namespace GitDeployer\Services;

use GuzzleHttp\Client;
use Symfony\Component\Console\Question\Question;

class GitLabService extends BaseService {
    
    /**
     * Holds the private key of this
     * user, used to authenticate to the API
     * @var string
     */
    protected $privateKey;

    /**
     * Holds the URL to the GitLab instance
     * @var string
     */
    protected $url;

    /**
     * Holds the user name for authentication
     * to the GitLab instance
     * @var string
     */
    protected $user;

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

        if (strlen($this->privateKey) > 0) {
            $client = $this->_createClient($this->privateKey);

            try {
                $response = $client->get('projects');
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
     * Get a list of projects from GitLab
     * @return array of \Git-Deployer\Objects\Project
     */
    public function getProjects($url = 'projects') {

        if (strlen($this->privateKey) > 0) {
            $client = $this->_createClient($this->privateKey);

            try {
                $response = $client->get($url);
                $projects = json_decode($response->getBody());

                $projects = array_map( function ($p) {
                    $project = new \GitDeployer\Objects\Project();
                    $project->name($p->name)
                            ->description($p->description)
                            ->url($p->http_url_to_repo);

                    return $project;
                }, $projects);

                if ($response->hasHeader('link')) {
                    // -> We have more than one page, extract the next
                    // page link and pass it to getProjects() again
                    $link = $response->getHeader('link')[0];
                    
                    preg_match('/<.*projects(.*)>; rel="next"/', $link, $matches);
                    
                    if (isset($matches[1])) {
                        $cleanLink = 'projects' . $matches[1];

                        $moreProjects = $this->getProjects($cleanLink);
                        if (is_array($moreProjects)) $projects = array_merge($projects, $moreProjects);
                    }
                }

                return $projects;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());            
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
     * @param  $key The private key to use, if any
     * @return \GuzzleHttp\Client
     */
    private function _createClient($key = null) {

        $config = [
            // Base URI is used with relative requests
            'base_uri' => $this->url . '/api/v3/'
        ];

        if ($key != null) {
            $config['headers'] = [
                'PRIVATE-TOKEN' => $key
            ];
        }

        $client = new Client($config);
        return $client;

    }

    /**
     * Logs into GitLab with user/password
     * @return boolean
     */
    private function _interactiveLogin() {

        $helper = $this->helpers->get('question');
        
        // -> Get GitLab instance URL
        $defText = (strlen($this->url) > 3 ? '[' . $this->url . '] ' : '' );
        $question = new Question('Please enter the URL to your GitLab instance: ' . $defText, $this->url);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 4) {
                throw new \RuntimeException(
                    'The URL for the GitLab instance can not be empty!'
                );
            }

            return $answer;
        });

        $this->url = $helper->ask($this->input, $this->output, $question);

        // -> Get GitLab login user
        $defText = (strlen($this->user) > 3 ? '[' . $this->user . '] ' : '' );
        $question = new Question('Please enter your GitLab username: ' . $defText, $this->user);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 4) {
                throw new \RuntimeException(
                    'The username can not be empty!'
                );
            }

            return $answer;
        });

        $this->user = $helper->ask($this->input, $this->output, $question);

        // -> Get GitLab password
        $question = new Question('Please enter your GitLab password: ');
        $question->setHidden(true);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 4) {
                throw new \RuntimeException(
                    'The password can not be empty!'
                );
            }

            return $answer;
        });

        $password = $helper->ask($this->input, $this->output, $question);

        // -> Now that we have al the necessary information, log us
        // into the GitLab instance
        $this->output->writeln('<info>Logging in to GitLab...</info>');
    
        $client = $this->_createClient();

        // Login is a FORM post
        try {
            $response = $client->post('session', [
                'form_params' => [
                    'login'     => $this->user,
                    'password'  => $password
                ]
            ]);

            if ($response->getStatusCode() == 201) {
                $json = json_decode($response->getBody());
                
                $this->privateKey = $json->private_token;
                $this->output->writeln('<comment>Hello ' . $json->name . '!</comment>');
                
                return true;
            } else {
                throw new \Exception($response->getStatusCode() . ' ' . $response->getReasonPhrase());
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());            
        }

    }

}