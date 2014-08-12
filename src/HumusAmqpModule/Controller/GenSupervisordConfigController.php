<?php

namespace HumusAmqpModule\Controller;

use Indigo\Supervisor\Configuration;
use Indigo\Supervisor\Section\InetHttpServerSection;
use Indigo\Supervisor\Section\ProgramSection;
use Indigo\Supervisor\Section\RpcInterfaceSection;
use Indigo\Supervisor\Section\SupervisorctlSection;
use Indigo\Supervisor\Section\SupervisordSection;
use Indigo\Supervisor\Section\UnixHttpServerSection;
use Zend\Console\ColorInterface;
use Zend\Mvc\Controller\AbstractConsoleController;
use Zend\Stdlib\ErrorHandler;

class GenSupervisordConfigController extends AbstractConsoleController
{
    public function indexAction()
    {
        $request = $this->getRequest();
        /* @var $request \Zend\Console\Request */

        $path = $request->getParam('path', getcwd() . '/supervisord.conf');

        if (substr($path, 0, 1) != '/') {
            $path = getcwd() . '/' . $path;
        }

        $config = $this->getServiceLocator()->get('Config');
        $moduleConfig = $config['humus_amqp_module'];
        $supervisordConfig = $config['humus_supervisor_module']['humus-amqp-supervisor']['supervisord'];

        $consumerTypes = array(
            'consumers', 'multiple_consumers', 'anon_consumers', 'rpc_servers'
        );

        $config = new Configuration();

        $section = new SupervisordSection($supervisordConfig['config']);
        $config->addSection($section);

        $section = new RpcInterfaceSection('supervisor', $supervisordConfig['rpcinterface']);
        $config->addSection($section);

        $section = new SupervisorctlSection($supervisordConfig['supervisorctl']);
        $config->addSection($section);

        $section = new UnixHttpServerSection($supervisordConfig['unix_http_server']);
        $config->addSection($section);

        $section = new InetHttpServerSection($supervisordConfig['inet_http_server']);
        $config->addSection($section);


        foreach ($consumerTypes as $consumerType) {
            $partConfig = $moduleConfig[$consumerType];

            // no config found, check next one
            if (empty($partConfig)) {
                continue;
            }

            foreach ($partConfig as $name => $part) {
                $section = new ProgramSection($name, array(
                    'process_name' => '%(program_name)s_%(host_node_name)s_%(process_num)02d',
                    'directory' => getcwd(),
                    'autostart' => true,
                    'autorestart' => true,
                    'numprocs' => 1,
                    'command' => 'php public/index.php humus amqp '
                        . strtolower(substr($consumerType, 0, -1)) . ' ' . $name
                ));

                if (isset($part['supervisord']) && is_array($part['supervisord'])) {
                    $options = array_merge($section->getOptions(), $part['supervisord']);
                    $section->setOptions($options);
                }

                $config->addSection($section);
            }
        }

        ErrorHandler::start();
        $rs = file_put_contents($path, $config->render());
        $error = ErrorHandler::stop();
        if (false === $rs || $error) {
            $this->getConsole()->writeLine('ERROR: Cannot write configuration to ' . $path, ColorInterface::RED);
            return null;
        }

        $this->getConsole()->writeLine('OK: configuration written to ' . $path, ColorInterface::GREEN);
        return null;
    }
}