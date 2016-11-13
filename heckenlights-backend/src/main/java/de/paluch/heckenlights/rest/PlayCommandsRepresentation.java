package de.paluch.heckenlights.rest;

import java.util.ArrayList;
import java.util.List;

import javax.xml.bind.annotation.XmlAccessType;
import javax.xml.bind.annotation.XmlAccessorType;
import javax.xml.bind.annotation.XmlElement;
import javax.xml.bind.annotation.XmlRootElement;

import lombok.Data;

/**
 * @author <a href="mailto:mpaluch@paluch.biz">Mark Paluch</a>
 * @since 02.12.13 18:15
 */
@XmlRootElement(name = "playCommands")
@XmlAccessorType(XmlAccessType.NONE)
@Data
public class PlayCommandsRepresentation {

    @XmlElement(name = "playCommand")
    private List<PlayCommandRepresentation> playCommands = new ArrayList<>();

    @XmlElement(name = "online")
    private boolean online;

    @XmlElement(name = "queueOpen")
    private boolean queueOpen;

    @XmlElement(name = "processingPlayback")
    private boolean processingPlayback;

}
