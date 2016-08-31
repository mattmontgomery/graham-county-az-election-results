<?php

require_once 'vendor/autoload.php';

use cli as cli;
use \GuzzleHttp\Client;

const ELECTIONS_URL = 'http://docs.graham.az.gov/ACC/Departments/Elections/ResultsFile/16EL45A.HTM';

class Output
{
    public function write($message = '')
    {
        cli\out($message . PHP_EOL);
    }
}

class Results
{
    protected $client;
    protected $output;
    public function __construct(Output $output)
    {
        $this->output = $output;
    }
    protected function getClient()
    {
        if (!$this->client) {
            $this->client = new Client();
        }
        return $this->client;
    }
    public function get()
    {
        $response = $this->getClient()->request('GET', ELECTIONS_URL);
        $responseBody = $response->getBody();
        $races = $this->getRaces($responseBody);
        $this->write('-----');
        foreach ($races as $raceName => $raceSet) {
            $this->write($raceName);
            $parsedCandidates = [];
            foreach ($raceSet['results'] as $candidates) {
            }
            foreach ($raceSet['results'] as $candidates) {
                foreach ($candidates as $candidate=>$candidateSet) {
                    if (empty($parsedCandidates[$candidate])) {
                        $parsedCandidates[$candidate] = [
                            'total'=>$candidateSet['total'] ?: 0,
                            'percent'=>(float) $candidateSet['percent'] ?: 0,
                            'electionDay'=>$candidateSet['electionDay'] ?: 0,
                            'earlyVoting'=>$candidateSet['earlyVoting'] ?: 0,
                            'verified'=>$candidateSet['verified'] ?: 0
                        ];
                    } else {
                        $parsedCandidates[$candidate] = [
                            'total'=>$parsedCandidates[$candidate]['total'] + $candidateSet['total'],
                            'percent'=>$parsedCandidates[$candidate]['percent'] + (float) $candidateSet['percent'],
                            'electionDay'=>$parsedCandidates[$candidate]['electionDay'] + $candidateSet['electionDay'],
                            'earlyVoting'=>$parsedCandidates[$candidate]['earlyVoting'] + $candidateSet['earlyVoting'],
                            'verified'=>$parsedCandidates[$candidate]['verified'] + $candidateSet['verified']
                        ];
                    }
                }
            }
            foreach ($parsedCandidates as $candidateName => $candidateResults) {
                $this->write("$candidateName: {$candidateResults['total']}");
            }
            $this->write('');
            $this->write('-----');
            $this->write('');
        }
    }
    public function getRaces($resultsTable)
    {
        $races = [];
        $table = preg_split("/(\r\n){2}/", $resultsTable);
        foreach ($table as $group) {
            $lines = preg_split("/(\r\n){1,}/", $group);
            if ($lines[0]) {
                if (strpos($lines[0], ' ') === 0 || strpos($lines[0], "\n") === 0) {
                    continue;
                }
                if (empty($races[$lines[0]])) {
                    $races[$lines[0]] = ['results'=>[], 'counted'=>''];
                }
                $races[$lines[0]]['counted'] = trim($lines[2]);
                $nextLines = array_splice($lines, 3);
                $candidates = [];
                foreach ($nextLines as $line) {
                    if (strpos($line, '  ') !== 0) {
                        $candidateName = substr($line, 0, strpos($line, '.  .'));
                        $candidateSet = preg_split("/\s{3,}/", $line);
                        try {
                            @list($_, $total, $percent, $electionDay, $earlyVoting, $verified) = $candidateSet;
                            $candidates[trim($candidateName)] = [
                                'total'=>$total,
                                'percent'=>$percent,
                                'electionDay'=>$electionDay,
                                'earlyVoting'=>$earlyVoting,
                                'verified'=>$verified
                            ];
                        } catch (\Exception $e) {
                            $this->write($e->getMessage());
                        }
                    }
                }
                $races[$lines[0]]['results'][] = $candidates;
            }
        }
        return $races;
    }
    public function write($message = '')
    {
        $this->output->write($message);
    }
}

function main()
{
    $output = new Output();
    $output->write('Checking for results');
    $results = new Results($output);
    $results->get();
}

main();
