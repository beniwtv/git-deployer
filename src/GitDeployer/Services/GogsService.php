<?php
namespace GitDeployer\Services;

use GuzzleHttp\Client;
use Symfony\Component\Console\Question\Question;

class GogsService extends BaseService {
    
    /**
     * Holds the token of this
     * user, used to authenticate to the API
     * @var string
     */
    protected $token;

    /**
     * Holds the URL to the Gogs instance
     * @var string
     */
    protected $url;

    /**
     * Holds the user name for authentication
     * to the Gogs instance
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

        if (strlen($this->token) > 0) {
            $client = $this->createClient($this->token);

            try {
                $client->get('projects');
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                // Login no longer valid?                
                if ($e->getResponse()->getStatusCode() == 401
                    || $e->getResponse()->getStatusCode() == 403) {
                    return $this->interactiveLogin();
                } else {
                    throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());            
                }
            }
        } else {
            return $this->interactiveLogin();
        }

    }

    /**
     * Get a list of projects from Gogs
     * @return array of \Git-Deployer\Objects\Project
     */
    public function getProjects($url = 'user/repos') {

        if (strlen($this->token) > 0) {
            $client = $this->createClient($this->token);

            try {
                $response = $client->get($url);
                $projects = json_decode($response->getBody());
                
                $projects = array_map( function ($p) {
                    $nametmp = explode('/', $p->full_name);

                    $project = new \GitDeployer\Objects\Project();
                    $project->id($p->id)
                            ->name($nametmp[1])
                            ->namespace($nametmp[0])
                            ->description($p->description)
                            ->url($p->clone_url)
                            ->homepage($p->html_url)
                            ->defaultBranch($p->default_branch);

                    return $project;
                }, $projects);

                return $projects;
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());            
            }
        } else {
            throw new \Exception('You must log-in to a service first!');            
        }

    }

    /**
     * Get a list of history items for a project from Gogs
     * @return array of \Git-Deployer\Objects\History
     */
    public function getHistory(\GitDeployer\Objects\Project $project, $url = 'projects/:id/repository/commits?page=0') {

        throw new \Exception('Not supported by Gogs yet!');            

    }

    /**
     * Get a list of tag items for a project from Gogs
     * @return array of \Git-Deployer\Objects\Tag
     */
    public function getTags(\GitDeployer\Objects\Project $project, $url = 'projects/:id/repository/tags') {

        return array();

    }

    //////////////////////////////////////////////////////////////////
    // Helpers
    //////////////////////////////////////////////////////////////////

    /**
     * Creates a new Guzzle HTTP client
     * @param  $key The private key to use, if any
     * @return \GuzzleHttp\Client
     */
    private function createClient($key = null) {

        $config = [
            // Base URI is used with relative requests
            'base_uri' => $this->url . '/api/v1/'
        ];

        if ($key != null) {
            $config['headers'] = [
                'Authorization' => 'token ' . $key
            ];
        }

        $client = new Client($config);
        return $client;

    }

    /**
     * Logs into Gogs with user/password
     * @return boolean
     */
    private function interactiveLogin() {

        $helper = $this->helpers->get('question');
        
        // -> Get Gogs instance URL
        $defText = (strlen($this->url) > 3 ? '[' . $this->url . '] ' : '' );
        $question = new Question('Please enter the URL to your Gogs instance: ' . $defText, $this->url);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 4) {
                throw new \RuntimeException(
                    'The URL for the Gogs instance can not be empty!'
                );
            }

            return $answer;
        });

        $this->url = $helper->ask($this->input, $this->output, $question);

        // -> Get Gogs login user
        $defText = (strlen($this->user) > 3 ? '[' . $this->user . '] ' : '' );
        $question = new Question('Please enter your Gogs username: ' . $defText, $this->user);
        $question->setValidator(function ($answer) {
            if (strlen($answer) < 4) {
                throw new \RuntimeException(
                    'The username can not be empty!'
                );
            }

            return $answer;
        });

        $this->user = $helper->ask($this->input, $this->output, $question);

        // -> Get Gogs password
        $question = new Question('Please enter your Gogs password: ');
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
        // into the Gogs instance
        $this->output->writeln('<info>Logging in to Gogs...</info>');
    
        $client = $this->createClient();

        // Login is a FORM post
        try {
            $response = $client->post('users/' . $this->user . '/tokens', [
                'auth'          => [
                    $this->user,
                    $password
                ],
                'form_params'   => [
                    'name'      => 'Access token for Git-Deployer'
                ]
            ]);

            if ($response->getStatusCode() == 201) {
                $json = json_decode($response->getBody());
                
                $this->token = $json->sha1;

                // Get user details
                $response = $client->get('users/' . $this->user, [
                    'headers' => [
                        'Authorization' => 'token ' . $this->token
                    ]
                ]);

                $json = json_decode($response->getBody());

                $this->output->writeln('<comment>Hello ' . $json->full_name . '!</comment>');
                
                return true;
            } else {
                throw new \Exception($response->getStatusCode() . ' ' . $response->getReasonPhrase());
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new \Exception($e->getResponse()->getStatusCode() . ' ' . $e->getResponse()->getReasonPhrase());            
        }

    }

    /**
     * Makes sure we only serialize needed data, else we may
     * put too much cruft in the serialized file that we can't restore
     * @return array
     */
    public function __sleep() {

        return array('token', 'url', 'user');
        
    }

}
