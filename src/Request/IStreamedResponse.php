<?php

namespace Runway\Request;

/**
 * A response whose body is sent as it's made (e.g. a big file assembled on the fly), not kept in memory: the kernel
 * sends the headers, then calls sendBody() instead of echoing getBody().
 */
interface IStreamedResponse extends IResponseRead {
    /**
     * Sends the body. Called once, after the headers are sent: an error cannot change the status any more.
     */
    public function sendBody(): void;
}
