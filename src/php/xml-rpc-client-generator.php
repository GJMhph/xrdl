<?php

ini_set("include_path", ini_get("include_path") . ":../lib/");

require_once "Args.class.php";

$a = new Args();
$file = $a->flag("file");

if ($file===FALSE) {
	printHelp();
}

$document = new DomDocument();
if (!is_readable($file)) {
	print "$file can not be read.\n";
	exit(1);
}
if ($document->load($file)===FALSE) {
	print "Failed to load service definition\n";
	exit(1);
}

$typeNodes = $document->getElementsByTagName("type");
$methodNodes = $document->getElementsByTagName("method");
$serviceNode = $document->documentElement;

$serviceName = $serviceNode->attributes->getNamedItem("name")->value;
$serviceUrl = $serviceNode->attributes->getNamedItem("url")->value;
$serviceNS = str_replace(".", "\\", $serviceNode->attributes->getNamedItem("ns")->value);

//print "/*\n";
//print "Service name: " . $serviceName . "\n";
//print "Service URL: " . $serviceUrl . "\n";
//print "Service NS: " . $serviceNS . "\n";
//print "Found " . $typeNodes->length . " type definitions\n";
//print "Found " . $methodNodes->length . " method definitions\n";
//print "*/\n\n";

$methods = "";
$types = "";

$clientCodeTemplate = <<<EOT
<?php

require_once "xmlrpc.inc";
require_once "xmlrpcs.inc";
require_once "xmlrpc_wrappers.inc";

//namespace %NAMESPACE%;

// Remotely defined types
%TYPES%

class %CLASSNAME% {
	private \$client;
	private \$url;

	public function __construct() {
		\$this->url = "%URL%";
		\$this->client = new xmlrpc_client("%URL%");
	}

	// Remote methods
	%METHODS%
}

?>
EOT;

for ($i=0;$i<$typeNodes->length;$i++) {
	$outputTypeDefinition = "class ";
	$typeNode = $typeNodes->item($i);
	$typeName = $typeNode->attributes->getNamedItem("name")->value;
	$outputTypeDefinition .= $typeName . " {\n";
	$memberNodes = $typeNode->childNodes;
	for ($j=0;$j<$memberNodes->length;$j++) {
		$memberNode = $memberNodes->item($j);
		if ($memberNode->nodeName=="member") {
			$memberType = $memberNode->attributes->getNamedItem("type")->value;
			$memberName = $memberNode->textContent;
			$outputTypeDefinition .= "\t// $memberType\n";
			$outputTypeDefinition .= "\tpublic $memberName;\n";
		} else {
			continue;
		}
	}
	$outputTypeDefinition .= "}\n\n";
	$types .= $outputTypeDefinition;
}

for ($i=0;$i<$methodNodes->length;$i++) {
	$outputMethodDefinition = "public function ";
	$methodNode = $methodNodes->item($i);
	$methodName = str_replace(".", "_", $methodNode->attributes->getNamedItem("name")->value);
	$outputMethodDefinition .= $methodName . "(";
	$paramNodes = $methodNode->childNodes;
	$paramCodeSnippet = "";
	$addedParamCount = 0;
	for ($j=0;$j<$paramNodes->length;$j++) {
		$paramNode = $paramNodes->item($j);
		if ($paramNode->nodeName=="param") {
			if ($addedParamCount>0) {
				$outputMethodDefinition .= ", ";
			}
			$paramType = $paramNode->attributes->getNamedItem("type")->value;
			$paramName = $paramNode->textContent;
			$outputMethodDefinition .= "\$" . $paramName;
			$paramCodeSnippet .= "\t\t\t\tnew xmlrpcval(\$$paramName, \"$paramType\"),\n";
			$addedParamCount++;
		} else {
			continue;
		}
	}
	$outputMethodDefinition .= <<<EOM
) {
                \$msg = new xmlrpcmsg("setInfoObjectHeadline",
                        array(
$paramCodeSnippet
                        )
                );
                \$response = \$this->client->send(\$msg, 15);
                if (\$response->faultCode()) {
                        return false;
                } else {
                        \$responseVal = \$response->value();
			\$cookies = \$response->cookies();
			foreach (\$cookies as \$key => \$value) {
				\$this->client->setcookie(\$key, \$value);
			}
                        return php_xmlrpc_decode($responseVal);
                }
	
	}
EOM;
	$methods .= $outputMethodDefinition;
}

$className = str_replace("-", "_", str_replace(" ", "_", $serviceName));

$clientCode = str_replace("%TYPES%", $types, $clientCodeTemplate);
$clientCode = str_replace("%NAMESPACE%", $serviceNS, $clientCode);
$clientCode = str_replace("%CLASSNAME%", $className . "_client", $clientCode);
$clientCode = str_replace("%METHODS%", $methods, $clientCode);
$clientCode = str_replace("%URL%", $serviceUrl, $clientCode);

print $clientCode;

function printHelp() {
	die("Usage: php ./xml-rpc-client-generator.php --file <path to XRDL-file>\n");
}

?>
