<?php

// todo proč se window.open kterej má ukázat erroru nezobrazí?
// todo zakázat uložení / nebo nějak to vymyslet když edituje a smaže celej text
// todo když hledá string nejde pak na překlad kliknout na poprvý

namespace LiveTranslator;

use Nette;

class Panel extends Nette\Object implements Nette\Diagnostics\IBarPanel
{

	const XHR_HEADER = 'X-Translation-Client';

	const LANGUAGE_KEY = 'X-LiveTranslator-Lang',
		NAMESPACE_KEY = 'X-LiveTranslator-Ns';

	/** @var string */
	protected $layout = 'vertical';

	/** @var int */
	protected $height = 465;

	/** @var Translator */
	protected $translator;

	/** @var Nette\Http\IRequest */
	protected $httpRequest;



	/**
	 * @param Translator $translator
	 * @param Nette\Http\IRequest $httpRequest
	 * @throws Nette\InvalidArgumentException
	 */
	public function __construct(Translator $translator, Nette\Http\IRequest $httpRequest)
	{
		$this->translator = $translator;
		$this->httpRequest = $httpRequest;

		$this->processRequest();
	}



	public function getLayout()
	{
		return $this->layout;
	}



	public function getHeight()
	{
		return $this->height;
	}



	public function getTranslator()
	{
		return $this->translator;
	}



	public function setLayout($layout)
	{
		if (!in_array($layout, array('horizontal', 'vertical'))){
			throw new Nette\InvalidArgumentException("Unknown layout $layout.");
		}
		$this->layout = $layout;
		return $this;
	}



	public function setHeight($height)
	{
		if (!is_numeric($height)){
			throw new Nette\InvalidArgumentException("Height must be integer.");
		}
		$this->height = $height;
		return $this;
	}



	/**
	 * Returns the code for the panel tab.
	 * @return string
	 */
	public function getTab()
	{
		$template = new Nette\Templating\FileTemplate(__DIR__ . '/tab.phtml');
		return $template->__toString();
	}



	/**
	 * Returns the code for the panel.
	 * @return string
	 */
	public function getPanel()
	{
		$file = $this->translator->isCurrentLangDefault() ? '/panel.inactive.phtml' : '/panel.phtml';
		$template = $this->createTemplate(__DIR__.$file);
		$template->panel = $this;
		$template->translator = $this->translator;
		$template->lang = $this->translator->getCurrentLang();
		if ($this->translator->getPresenterLanguageParam()){
			$template->availableLangs = $this->translator->getAvailableLanguages();
		}
		else {
			$template->availableLangs = NULL;
		}

		return $template->__toString();
	}



	public function getLink($toLang)
	{
		return $this->translator->getPresenterLink($toLang);
	}



	/**
	 * Handles incoming request and sets translations.
	 */
	private function processRequest()
	{
		if ($this->httpRequest->isPost() && $this->httpRequest->isAjax() && $this->httpRequest->getHeader(self::XHR_HEADER)) {
			$data = json_decode(file_get_contents('php://input'));

			if ($data) {
				$this->translator->setCurrentLang($data->{self::LANGUAGE_KEY});
				if ($data->{self::NAMESPACE_KEY}) $this->translator->setNamespace($data->{self::NAMESPACE_KEY});

				unset($data->{self::LANGUAGE_KEY}, $data->{self::NAMESPACE_KEY});

				foreach ($data as $string => $translated){
					$this->translator->setTranslation($string, $translated);
				}
			}
			exit;
		}
	}


	private function createTemplate($file)
	{
		$template = new Nette\Templating\FileTemplate($file);
		$latte = new Nette\Latte\Engine;
		Nette\Latte\Macros\CoreMacros::install($latte->getCompiler());
		$template->registerHelperLoader('Nette\Templating\Helpers::loader')
			->registerHelper('ordinal', function($n){
				switch (substr($n, -1)) {
					case 1:
						return 'st';
					case 2:
						return 'nd';
					case 3:
						return 'rd';
					default:
						return 'th';
				}
			})
			->registerFilter($latte)
		;
		return $template;
	}
}
