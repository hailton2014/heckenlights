package biz.paluch.heckenlights.messagebox.application;

import java.awt.*;
import java.awt.image.BufferedImage;
import java.awt.image.DataBuffer;
import java.awt.image.SampleModel;
import java.io.File;
import java.io.IOException;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;
import java.util.concurrent.TimeUnit;

import javax.imageio.ImageIO;
import javax.inject.Inject;
import javax.media.jai.RasterFactory;
import javax.media.jai.TiledImage;

import org.joda.time.Duration;
import org.joda.time.format.PeriodFormatter;
import org.joda.time.format.PeriodFormatterBuilder;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import org.springframework.util.StringUtils;

import biz.paluch.heckenlights.messagebox.client.midirelay.MidiRelayClient;
import biz.paluch.heckenlights.messagebox.client.midirelay.PlayerStateRepresentation;
import biz.paluch.heckenlights.messagebox.client.midirelay.PlayerStateTrackRepresentation;
import lombok.extern.slf4j.Slf4j;

/**
 * @author <a href="mailto:mpaluch@paluch.biz">Mark Paluch</a>
 */
@Service
@Slf4j
public class GetCurrentTitle {

    @Value("${image.height}")
    private int height;

    @Value("${image.width.min}")
    private int minWidth;

    @Value("${image.width.preroll:0}")
    private int widthPreroll;

    @Value("${image.width.postroll:0}")
    private int widthPostroll;

    @Inject
    private MidiRelayClient midiRelayClient;

    public String getCurrentTitle() {
        try {
            PlayerStateRepresentation state = midiRelayClient.getState();

            if (state != null && state.isRunning() && state.getTrack() != null) {
                String currentTitle = getCurrentTitle(state);
                log.info("Current title: " + currentTitle);
                return currentTitle;
            }

        } catch (Exception e) {
            log.warn(e.getMessage(), e);
        }
        return null;
    }

    private String getCurrentTitle(PlayerStateRepresentation state) {
        PlayerStateTrackRepresentation track = state.getTrack();

        String suffix = "";
        if (state.getEstimatedSecondsToPlay() > 0) {
            Duration duration = new Duration(TimeUnit.SECONDS.toMillis(state.getEstimatedSecondsToPlay())); // in
                                                                                                            // milliseconds
            PeriodFormatter formatter = new PeriodFormatterBuilder().minimumPrintedDigits(1).printZeroAlways().appendMinutes()
                    .appendLiteral(":").minimumPrintedDigits(2).printZeroAlways().appendSeconds().toFormatter();
            suffix = " (" + formatter.print(duration.toPeriod()) + ")";
        }

        if (StringUtils.hasText(track.getFileName()) && StringUtils.hasText(track.getSequenceName())) {
            return track.getSequenceName() + suffix;
        }

        if (StringUtils.hasText(track.getFileName())) {
            return track.getFileName().trim() + suffix;
        }

        if (StringUtils.hasText(track.getSequenceName())) {
            return track.getSequenceName().trim() + suffix;
        }

        return null;
    }

    public byte[] getCurrentTitleImage(String format) throws IOException {

        String title = getCurrentTitle();
        String suffix = "via http://heckenlights.org";
        if (title == null) {
            return null;
        }

        List<String> parts = new ArrayList<>();
        parts.add(title);

        Renderer renderer = new Renderer(new Color(200, 200, 200));

        int width = Math.max(minWidth, renderer.getWidth(Arrays.asList(title, suffix))) + widthPreroll + widthPostroll + 8 + 14
                + height;

        // We need a sample model for color images where the pixels are bytes, with three bands.
        SampleModel sampleModel = RasterFactory.createBandedSampleModel(DataBuffer.TYPE_BYTE, width, height, 3);

        TiledImage tiledImage = new TiledImage(0, 0, width, height, 0, 0, sampleModel, null);
        Graphics2D graphics = tiledImage.createGraphics();

        BufferedImage image = ImageIO.read(new File("assets/note.png"));

        graphics.drawImage(image, (int) (widthPreroll + 1), 0, null);

        int offset = renderer.runGraphics(widthPreroll + 8 + height, parts, graphics);

        renderer.setForeground(new Color(220, 44, 43));
        renderer.runGraphics(offset + 12, Arrays.asList(suffix), graphics);

        graphics.dispose();

        return ImageEncoder.encode(format, tiledImage);
    }
}
