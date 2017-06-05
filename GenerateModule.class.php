<?php
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class GenerateModuleCommand extends Command{
	protected function configure(){
		$this->setName('generate');
		$this->setDescription('Creates a skeleton module for FreePBX 13+');
		$this->setHelp('This command creates a new module in the present working directory');
	}

	protected function execute(InputInterface $input, OutputInterface $output){
		$this->output = $output;
		$this->input = $input;
		$helper = $this->getHelper('question');
		$question = new Question('What is your module\'s name no spaces? ', 'helloworld');
		$question->setNormalizer(function ($value) {
			return strtolower(trim($value));
		});
		$this->rawname  = $helper->ask($input, $output, $question);
		$question = new Question('What is your module\'s version? ', '13.0.1');
		$this->version =  $helper->ask($input, $output, $question);
		$question = new Question('What is your module\'s description? ', 'Generated Module');
		$this->description = $helper->ask($input, $output, $question);
		$question = new ChoiceQuestion('What is the license for this module?',
											array('GPLv2','GPLv3','AGPLv3','MIT'),2);
		$this->license =  $helper->ask($input, $output, $question);
		$question = new ChoiceQuestion('What type of module is this?',
											array('Admin','Applications','Connectivity','Reports','Settings'),2);
		$this->apptype =  $helper->ask($input, $output, $question);
		$qtext = "Generate a module with the following information?".PHP_EOL;
		$qtext .= "Module rawname: " .$this->rawname.PHP_EOL;
		$qtext .= "Module version: " .$this->version.PHP_EOL;
		$qtext .= "Module description: " .$this->description.PHP_EOL;
		$qtext .= "Module type: " .$this->apptype.PHP_EOL;
		$qtext .= "Module License: " .$this->license.PHP_EOL;
		$qtext .= "Type [yes|no]:";
		$question = new ConfirmationQuestion($qtext, false);
		if (!$helper->ask($input, $output, $question)) {
			return;
		}
		$this->basedir = getcwd().'/'.$this->rawname;
		$this->makeFileStructure();
		$this->touchFiles();
		$this->makeModuleXML();
		$this->makeBMO();
		$this->makePage();
		$this->copyLic();
	}
	protected function makeFileStructure(){
		$this->output->writeln("Generating Directories for your module");
		$fs = new Filesystem();
		$directories = array(
			$this->basedir .'/views',
			$this->basedir .'/assets/css',
			$this->basedir .'/assets/js',
		);
		$fs->mkdir($directories);

	}
	protected function touchFiles(){
		$this->output->writeln("Generating File structure for your module");
		$fs = new Filesystem();
		$uppername = ucfirst($this->rawname);
		$files = array(
			$this->basedir .'/'.$uppername.'.class.php',
			$this->basedir .'/install.php',
			$this->basedir .'/uninstall.php',
			$this->basedir .'/module.xml',
			$this->basedir .'/page.'.$this->rawname.'.php',
			$this->basedir .'/assets/css/'.$this->rawname.'.css',
			$this->basedir .'/assets/js/'.$this->rawname.'.js',
			$this->basedir .'/views/main.php'
		);
		$fs->touch($files);
	}
	protected function makeModuleXML(){
		$this->output->writeln("Generating module.xml");

		$data = array(
			'rawname' => $this->rawname,
			'name' => ucfirst($this->rawname),
			'version' => $this->version,
			'publisher' => 'Generated Module',
			'license' => $this->license,
			'changelog' => '*'.$this->version.'* Initial release',
			'category' => $this->apptype,
			'description' => $this->description,
			'menuitems' => array($this->rawname => ucfirst($this->rawname)),
			'supported' => '13.0'
		);
		$xml = new SimpleXMLElement('<module/>');
		foreach ($data as $key => $value) {
			if($key == 'menuitems'){
				$menu = $xml->addChild($key);
				foreach ($value as $k => $v) {
					$menu->addChild($k,$v);
				}
			}else{
				$xml->addChild($key,$value);
			}
		}
		$dom = dom_import_simplexml($xml)->ownerDocument;
		$dom->formatOutput = true;
		$out = $dom->saveXML();
		$out = str_replace('<?xml version="1.0"?>', '', $out);
		file_put_contents ( $this->basedir.'/module.xml', $out);
	}
	protected function makeBMO(){
		$this->output->writeln("Generating BMO class");
		$uppername = ucfirst($this->rawname);
		$template = file(__DIR__.'/resources/BMO.template');
		$bmofile =  fopen($this->basedir .'/'.$uppername.'.class.php',"w");
		$find = array('##RAWNAME##','##CLASSNAME##');
		$replace = array($this->rawname, $uppername);
		foreach ($template as $line) {
			$out = str_replace($find, $replace, $line);
			fwrite($bmofile, $out);
		}
		fclose($bmofile);
	}
	protected function makePage(){
		$this->output->writeln("Generating module page and view");
		$uppername = ucfirst($this->rawname);
		$template = file(__DIR__.'/resources/page.template');
		$pagefile =  fopen($this->basedir .'/page.'.$this->rawname.'.php',"w");
		$find = array('##RAWNAME##','##CLASSNAME##');
		$replace = array($this->rawname, $uppername);
		foreach ($template as $line) {
			$out = str_replace($find, $replace, $line);
			fwrite($pagefile, $out);
		}
		fclose($pagefile);
		$view = fopen($this->basedir .'/views/main.php',"w");
		fwrite($view, "IT WORKS!!!! Generated for ".$uppername);
		fclose($view);
	}
	protected function copyLic(){
		$fs = new Filesystem();
		$fs->copy(__DIR__.'/resources/'.$this->license, $this->basedir .'/LICENSE');
	}
}
