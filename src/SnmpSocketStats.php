<?php

namespace IMEdge\SnmpFeature;

class SnmpSocketStats
{
    public int $cntGetRequests = 0;
    public int $cntGetBulkRequests = 0;
    public int $cntGetNextRequests = 0;
    public int $cntWalkRequests = 0;
    public int $oidsRequestedGet = 0;
    public int $oidsRequestedGetBulk = 0;
    public int $oidsRequestedGetNext = 0;
    public int $oidsRequestedWalk = 0;
    public int $oidsReceived = 0;
    public int $responsesReceived = 0;
    public int $cntTimeouts = 0;

    public function getStats(): array
    {
        return [
            'GetRequests'          => $this->cntGetRequests,
            'GetBulkRequests'      => $this->cntGetBulkRequests,
            'GetNextRequests'      => $this->cntGetNextRequests,
            'WalkRequests'         => $this->cntWalkRequests,
            'RequestedOidsGet'     => $this->oidsRequestedGet,
            'RequestedOidsGetBulk' => $this->oidsRequestedGetBulk,
            'RequestedOidsGetNext' => $this->oidsRequestedGetNext,
            'RequestedOidsWalk'    => $this->oidsRequestedWalk,
            'ReceivedOids'         => $this->oidsReceived,
            'ReceivedResponses'    => $this->responsesReceived,
            'Timeouts'             => $this->cntTimeouts,
        ];
    }
}
