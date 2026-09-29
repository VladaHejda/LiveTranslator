<?php
declare(strict_types=1);

// todo proč se window.open kterej má ukázat erroru nezobrazí?
// todo zakázat uložení / nebo nějak to vymyslet když edituje a smaže celej text
// todo když hledá string nejde pak na překlad kliknout na poprvý

namespace LiveTranslator\Panel;

use LiveTranslator\Translator;
use Nette;
use Latte;

class Panel implements \Tracy\IBarPanel
{
	use Nette\SmartObject;

	const XHR_HEADER = 'X-Translation-Client';

	const LANGUAGE_KEY = 'X-LiveTranslator-Lang',
		NAMESPACE_KEY = 'X-LiveTranslator-Ns';

	protected string $layout = 'vertical';

	protected int $height = 465;

	protected Translator $translator;

	protected Nette\Http\IRequest $httpRequest;



	/**
	 * @throws Nette\InvalidArgumentException
	 */
	public function __construct(Translator $translator, Nette\Http\IRequest $httpRequest)
	{
		$this->translator = $translator;
		$this->httpRequest = $httpRequest;

		$this->processRequest();
	}



	public function getLayout(): string
	{
		return $this->layout;
	}



	public function getHeight(): int
	{
		return $this->height;
	}



	public function getTranslator(): Translator
	{
		return $this->translator;
	}



	public function setLayout(string $layout): self
	{
		if (!in_array($layout, array('horizontal', 'vertical'))){
			throw new Nette\InvalidArgumentException("Unknown layout $layout.");
		}
		$this->layout = $layout;
		return $this;
	}



	public function setHeight(int|string $height): self
	{
		if (!is_numeric($height)){
			throw new Nette\InvalidArgumentException("Height must be integer.");
		}
		$this->height = (int) $height;
		return $this;
	}



	/**
	 * Returns the code for the panel tab.
	 */
	public function getTab(): string
	{
		//$template = new Nette\Templating\FileTemplate(__DIR__ . '/tab.phtml');
		//return $template->__toString();

		$latte = $this->createTemplate();
		return $latte->renderToString(__DIR__ . '/tab.phtml'
		);
	}



	/**
	 * Returns the code for the panel.
	 */
	public function getPanel(): string
	{
		$latte = $this->createTemplate();
		$file = $this->translator->isCurrentLangDefault() ? '/panel.inactive.phtml' : '/panel.phtml';
		$parameters = array();
		$parameters['panel'] = $this;
		$parameters['translator'] = $this->translator;
		$parameters['lang'] = $this->translator->getCurrentLang();
		if ($this->translator->getPresenterLanguageParam()){
			$parameters['availableLangs'] = $this->translator->getAvailableLanguages();
		}
		else {
			$parameters['availableLangs'] = null;
		}
		return $latte->renderToString(__DIR__ . $file, $parameters);
	}



	public function getLink(string $toLang): ?string
	{
		return $this->translator->getPresenterLink($toLang);
	}



	/**
	 * Handles incoming request and sets translations.
	 */
	private function processRequest(): void
	{
		if ($this->httpRequest->isMethod('post') && $this->httpRequest->isAjax() && $this->httpRequest->getHeader(self::XHR_HEADER)) {
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


	private function createTemplate(): Latte\Engine
	{
		$latte = new Latte\Engine;
		$latte->addFilter('ordinal', function (int|string $n): string {
			switch (substr((string) $n, -1)) { // plural variant number - int under strict_types
				case 1:
					return 'st';
				case 2:
					return 'nd';
				case 3:
					return 'rd';
				default:
					return 'th';
			}
		});

		return $latte;
	}
}
