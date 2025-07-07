### Surface Front Position Placefile
This project generates a placefile containing the last reported Surface Front Positions provided by the National Weather Service. 
This placefile can be used with GRLevel3, GRAnalyst and SupercellWX. When developing this script, I didn't concern myself with 
ensuring the colors of the lines matched expectations in the weather community, so I've placed this project on Github in case 
someone wishes to build on this script.

## How does it work?
The National Weather Service posts updated Surface Front Positions to their FTP server about every three hours. There are 
sometimes corrections that are pushed, as well. Since this data does not change often, I didn't want to redownload this 
information from the NWS every single time this script was requested. Originally, I generated a contab job that downloaded the 
file every 30 minutes. In the interest of providing an all-in-one project, I've added a caching feature to the script where it 
will download the file if it is older than the configured `MaxCodsusAge`. The script then generates the lines showing the 
location of the fronts and displays the corresponding Highs and Lows on the map using the supplied icon files in the icons folder.

## What are the configuration options?
- `$RefreshMinutes` is the interval for the placefile to reload itself. Again, since the data isn't updated frequently, it
                    seemed rather silly to set this too frequently.
- `$IconSet`. There are two (2) icon files included that contain the H and L images for Highs and Lows. One is the traditional
                    red and blue, while the other contains a more bright blue (cyan) which may be desirable with some
                    backgrounds.
- `$IconSize`. Six different sizes of the H and L icons exist within each of the icon files ranging from small (1) to
                    largest (6). The largest icon is about 40 pixels squared and is the default.
- `$MaxCodsusAge`, as discussed earlier, is the frequency with which the script will download the CODSUS product from the NWS
                    FTP server. By default, it's set at every 30 minutes.
- `$DeleteOld` indicates whether to retain the downloaded CODSUS data files or delete them as soon as we download a new one.
                    Most likely, you'll want to simply delete the old ones, but sometimes, you may wish to retain a historical
                    record of these files.
- `$ShowFileData` adds some extra lines the generated placefile to help with debugging. As each line is processed from the
                    CODSUS data file, that line will be added as a comment within the placefile so you can review how the
                    corresponding placefile objects and lines were created.

## Additional Notes
I've generated a number of placefile scripts over the years so there is a placefile_common file that handles a number of the
common methods. You can easily join these into the the front_positions.php file and run it through a single script file, but
I've kept them separate.
