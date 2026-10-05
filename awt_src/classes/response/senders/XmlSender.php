<?php

namespace response\senders;

class XmlSender extends AbstractSender
{
    public function supportedMimes(): array
    {
        return ['application/xml', 'text/xml'];
    }

    /**
     * $payload may be:
     *  - string path to an .xml file
     *  - string raw XML
     *  - array  → auto-converted to XML
     */
    public function send(mixed $payload, int $status, array $headers): void
    {
        $this->emitStatus($status);
        $this->emitContentType('application/xml; charset=UTF-8');
        $this->emitHeaders($headers);

        if (is_array($payload)) {
            echo $this->arrayToXml($payload);
        } elseif (is_string($payload) && file_exists($payload)) {
            $this->streamFile($payload);
        } else {
            echo (string) $payload;
        }
    }

    private function arrayToXml(array $data, string $rootElement = 'response'): string
    {
        $xml = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><$rootElement/>");
        $this->buildXml($xml, $data);
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    private function buildXml(\SimpleXMLElement $xml, array $data): void
    {
        foreach ($data as $key => $value) {
            $tag = is_numeric($key) ? 'item' : $key;
            if (is_array($value)) {
                $child = $xml->addChild($tag);
                $this->buildXml($child, $value);
            } else {
                $xml->addChild($tag, htmlspecialchars((string) $value, ENT_XML1));
            }
        }
    }
}
