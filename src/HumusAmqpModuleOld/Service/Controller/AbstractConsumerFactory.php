<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace HumusAmqpModuleOld\Service\Controller;

use HumusAmqpModuleOld\Controller\ConsumerManagerAwareInterface;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

abstract class AbstractConsumerFactory implements FactoryInterface
{
    /**
     * Create service
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $sm = $serviceLocator->getServiceLocator();
        $class = $this->getControllerClass();
        $controller = new $class();
        return $this->injectConsumerPluginManager($controller, $sm);
    }

    /**
     * @return string
     */
    abstract protected function getConsumerType();

    /**
     * @return string
     */
    abstract protected function getControllerClass();

    /**
     * @param ConsumerManagerAwareInterface $controller
     * @param ServiceLocatorInterface $serviceLocator
     * @return ConsumerManagerAwareInterface
     */
    protected function injectConsumerPluginManager(
        ConsumerManagerAwareInterface $controller,
        ServiceLocatorInterface $serviceLocator
    ) {
        /** @var ServiceLocatorInterface $manager */
        $manager = $serviceLocator->get($this->getConsumerType());
        $controller->setConsumerManager($manager);
        return $controller;
    }
}